<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ChecksIfMatch
{
    /**
     * Require a matching If-Match header before allowing a mutation, so a
     * client that read a stale version can't silently overwrite a newer one.
     */
    protected function assertIfMatch(Request $request, string $currentEtag): void
    {
        $ifMatch = $request->header('If-Match');

        if (! $ifMatch) {
            throw new HttpException(428, 'If-Match header is required.');
        }

        if ($ifMatch !== $currentEtag) {
            throw new HttpException(412, 'Resource has changed since it was last read.');
        }
    }
}
