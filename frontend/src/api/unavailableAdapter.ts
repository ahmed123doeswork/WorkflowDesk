import { ApiError, type WorkflowDeskApi } from './types'

/**
 * Used when the live API can't be reached. Every call fails the same, named
 * way, so views can show one clear "can't reach the backend" state instead
 * of a tangle of per-call error handling.
 */
export class UnavailableAdapter implements WorkflowDeskApi {
  readonly mode = 'unavailable' as const

  private fail(): never {
    throw new ApiError('The WorkflowDesk API is unreachable right now.', 0)
  }

  login = () => this.fail()
  logout = () => this.fail()
  me = () => this.fail()
  listEnquiries = () => this.fail()
  getEnquiry = () => this.fail()
  updateEnquiry = () => this.fail()
  assignEnquiry = () => this.fail()
  transitionEnquiry = () => this.fail()
  createEnquiry = () => this.fail()
  listUsers = () => this.fail()
  auditTrail = () => this.fail()
  verifyAuditChain = () => this.fail()
}
