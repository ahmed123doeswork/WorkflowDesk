<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryStatus;
use App\Enums\Priority;
use App\Http\Concerns\ChecksIfMatch;
use App\Models\AuditLog;
use App\Models\Enquiry;
use App\Services\AuditChain;
use App\Services\SlaCalculator;
use App\StateMachines\EnquiryTransitions;
use App\Support\Clock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnquiryController extends Controller
{
    use ChecksIfMatch;

    public function __construct(
        private readonly SlaCalculator $sla,
        private readonly Clock $clock,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Enquiry::class);

        $query = Enquiry::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->query('assigned_to'));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('student_name', 'like', "%{$search}%");
            });
        }

        return $query->latest()
            ->paginate(min((int) $request->integer('per_page', 15), 100));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Enquiry::class);

        $validated = $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'student_email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
        ]);

        $enquiry = DB::transaction(function () use ($validated, $request) {
            $tenant = $request->user()->tenant;
            $priority = Priority::from($validated['priority'] ?? 'medium');
            $dueDates = $this->sla->dueDates($tenant, $priority);

            $enquiry = Enquiry::create([
                ...$validated,
                'created_by' => $request->user()->id,
                'version' => 1,
                'response_due_at' => $dueDates['response_due_at'],
                'resolution_due_at' => $dueDates['resolution_due_at'],
            ]);

            AuditChain::record('enquiry.created', $enquiry, ['after' => $validated]);

            return $enquiry;
        });

        return response()->json($enquiry, 201)->withHeaders([
            'ETag' => $enquiry->etag(),
        ]);
    }

    public function show(Enquiry $enquiry)
    {
        $this->authorize('view', $enquiry);

        return response()->json($enquiry)->withHeaders([
            'ETag' => $enquiry->etag(),
        ]);
    }

    public function auditTrail(Enquiry $enquiry)
    {
        $this->authorize('view', $enquiry);

        return AuditLog::withoutGlobalScopes()
            ->where('auditable_type', Enquiry::class)
            ->where('auditable_id', $enquiry->id)
            ->with('user:id,name')
            ->orderBy('id')
            ->get();
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $this->authorize('update', $enquiry);
        $this->assertIfMatch($request, $enquiry->etag());

        $validated = $request->validate([
            'student_name' => ['sometimes', 'string', 'max:255'],
            'student_email' => ['sometimes', 'email'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
        ]);

        DB::transaction(function () use ($enquiry, $validated) {
            $enquiry->update($validated);

            AuditChain::record('enquiry.updated', $enquiry, AuditChain::diff($enquiry, array_keys($validated)));
        });

        return response()->json($enquiry->refresh())->withHeaders([
            'ETag' => $enquiry->etag(),
        ]);
    }

    public function assign(Request $request, Enquiry $enquiry)
    {
        $this->authorize('assign', $enquiry);
        $this->assertIfMatch($request, $enquiry->etag());

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($enquiry, $validated) {
            $enquiry->update(['assigned_to' => $validated['assigned_to'] ?? null]);

            AuditChain::record('enquiry.assigned', $enquiry, AuditChain::diff($enquiry, ['assigned_to']));
        });

        return response()->json($enquiry->refresh())->withHeaders([
            'ETag' => $enquiry->etag(),
        ]);
    }

    public function transition(Request $request, Enquiry $enquiry)
    {
        $this->authorize('transition', $enquiry);
        $this->assertIfMatch($request, $enquiry->etag());

        $validated = $request->validate([
            'status' => ['required', 'in:new,in_progress,waiting,resolved,closed'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $to = EnquiryStatus::from($validated['status']);

        if (! EnquiryTransitions::canTransition($enquiry->status, $to)) {
            throw new HttpException(422, "Cannot transition from {$enquiry->status->value} to {$to->value}.");
        }

        DB::transaction(function () use ($enquiry, $to, $validated) {
            $attributes = ['status' => $to];

            if ($to === EnquiryStatus::InProgress && ! $enquiry->responded_at) {
                $attributes['responded_at'] = $this->clock->now();
            }

            if ($to === EnquiryStatus::Resolved) {
                $attributes['resolved_at'] = $this->clock->now();
            }

            $enquiry->update($attributes);

            $diff = AuditChain::diff($enquiry, array_keys($attributes));

            if (! empty($validated['note'])) {
                $diff['note'] = $validated['note'];
            }

            AuditChain::record('enquiry.status_changed', $enquiry, $diff);
        });

        return response()->json($enquiry->refresh())->withHeaders([
            'ETag' => $enquiry->etag(),
        ]);
    }
}
