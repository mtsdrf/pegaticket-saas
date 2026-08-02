export type WorkflowEntityType = 'sale'

export interface WorkflowTransitionLogUser {
  uuid: string
  name: string
  email: string
}

export interface WorkflowTransitionLog {
  uuid: string
  workflow_type: WorkflowEntityType
  entity_uuid: string
  from_stage: string | null
  to_stage: string | null
  transition_type: string
  reason: string | null
  meta: Record<string, unknown> | null
  moved_at: string | null
  user?: WorkflowTransitionLogUser | null
}
