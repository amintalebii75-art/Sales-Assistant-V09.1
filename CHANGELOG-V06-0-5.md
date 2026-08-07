# V06.0.5 — Team Result Options, Call-Center Handoff, IRANSansXFaNum

## Changes

- Added a role-aware `+` control beside negotiation/call result options.
- Only a real Manager or a linked team member whose organizational access is `sales_manager` can create a new shared result option.
- Shared options are persisted server-side with CSRF, optimistic revision locking, backup, and audit.
- Manager-created options are visible to call center and sales users.
- Call-center users can select manager-created options; arbitrary purchase/order outcomes remain blocked by backend policy.
- A call-center interaction now creates a deduplicated handoff task for the marketer assigned to that customer.
- Scoped server state is now authoritative for reply options, preventing filtered order outcomes from being re-added by local default merging.
- Clarified call-center form labels:
  - Customer-stated approximate volume
  - Customer expected price
- Added role-aware wording for the operations entry point.
- UI font stack changed to `IRANSansXFaNum` with local font-file references.

## Database

No SQL migration is required.
