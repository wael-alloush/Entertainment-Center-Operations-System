export interface SessionDuration {
    id: number;
    name: string;
    color: string;
    duration_minutes: number | null;
}

export interface InvoiceExtension {
    id: number;
    session_duration: SessionDuration;
}

export interface BonusProgramSettings {
    visit_threshold: number;
    validity_days?: number;
    expiry_check_time?: string;
    next_display_number?: number;
}

export interface Invoice {
    id: number;
    invoice_number: string;
    invoice_display_label: string;
    is_bonus: boolean;
    is_expired_bonus?: boolean;
    is_credit?: boolean;
    bonus_display_number: number | null;
    credit_display_number?: number | null;
    counts_as_visit?: boolean;
    is_bonus_eligible: boolean;
    has_pending_bonus: boolean;
    children_count: number;
    phone_number: string;
    visit_count: number;
    status: string;
    session_duration: SessionDuration;
    extensions: InvoiceExtension[];
    entered_at: string;
    expected_exit_at: string | null;
    expected_exit_at_iso?: string | null;
    exited_at: string | null;
    expired_at?: string | null;
    remaining_minutes: number | null;
}

export interface CustomerBonus {
    id: number;
    title: string;
    display_number: number | null;
    children_count: number | null;
    session_duration: SessionDuration | null;
    created_at: string;
}

export interface InvoiceDetail extends Invoice {
    customer_id: number | null;
    entered_at_full: string;
    expected_exit_at_full: string | null;
    exited_at_full: string | null;
    status_label: string;
    bonus_count: number;
    bonuses: CustomerBonus[];
}

export interface PreviousVisitSessionBadge {
    name: string;
    color: string;
}

export interface PreviousVisitInvoice {
    id: number;
    invoice_number: string;
    invoice_display_label: string;
    is_expired_bonus?: boolean;
    is_bonus?: boolean;
    is_credit?: boolean;
    children_count: number;
    original_duration_minutes: number;
    extensions_duration_minutes: number;
    total_duration_minutes: number;
    original_session: PreviousVisitSessionBadge;
    extensions: PreviousVisitSessionBadge[];
    visit_date: string | null;
}

export interface PaginatedPreviousVisitInvoices {
    data: PreviousVisitInvoice[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface PreviousVisitsPayload {
    phone_number: string;
    visit_count: number;
    average_children: number;
    average_children_visit_threshold: number;
    invoices: PaginatedPreviousVisitInvoices;
}

export interface InvoiceFilters {
    search: string;
    from_date?: string;
    to_date?: string;
}

export interface InvoiceSummary {
    invoice_count: number;
    children_count: number;
}

export interface ActiveInvoiceChanges {
    synced_at: string;
    updated: Invoice[];
    removed_ids: number[];
    summary: InvoiceSummary | null;
}

export interface EligibleBonusCustomer {
    id: number;
    phone_number: string;
    visit_count: number;
}

export interface PaginatedEligibleBonusCustomers {
    data: EligibleBonusCustomer[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface PendingBonus {
    id: number;
    phone_number: string;
    children_count: number;
    visit_count: number;
    session_duration: SessionDuration;
    entered_at: string;
    expires_at: string | null;
}

export interface PendingCredit {
    id: number;
    phone_number: string;
    children_count: number;
    visit_count: number;
    counts_as_visit: boolean;
    session_duration: SessionDuration;
    entered_at: string;
}

export interface PaginatedPendingCredits {
    data: PendingCredit[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface PaginatedPendingBonuses {
    data: PendingBonus[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface PaginatedInvoices {
    data: Invoice[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface EmployeeAttendance {
    id: number;
    name: string;
    check_in: string;
    check_out: string;
    late_minutes: number | null;
    has_checked_in: boolean;
    has_checked_out: boolean;
    is_day_off: boolean;
    is_automatic_day_off?: boolean;
    can_manage_day_off: boolean;
    attendance_records_count?: number;
}

export interface ManagedSessionDuration {
    id: number;
    name: string;
    duration_minutes: number | null;
    color: string;
    sort_order: number;
    is_active: boolean;
    is_open: boolean;
    invoices_count: number;
}

export interface ManagedEmployee {
    id: number;
    name: string;
    is_active: boolean;
    attendance_records_count: number;
}

export interface ManagedUser {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'reception';
    role_label: string;
    is_active: boolean;
}

export interface DeviceTrustSettings {
    enabled: boolean;
}

export interface AttendanceDeviceRestrictionSettings {
    enabled: boolean;
}

export interface ManagedTrustedDevice {
    id: number;
    name: string;
    user_agent: string | null;
    is_active: boolean;
    allows_attendance: boolean;
    is_currently_active: boolean;
    last_used_at: string | null;
    created_at: string | null;
    created_by_name: string | null;
}

export interface PendingDeviceCode {
    id: number;
    label: string | null;
    expires_at: string;
    created_at: string | null;
    created_by_name: string | null;
}

export interface EmployeeAttendanceRecord {
    id: number;
    work_date: string;
    work_day_name?: string;
    scheduled_start: string;
    checked_in_at: string;
    checked_out_at: string;
    late_label: string;
    is_day_off?: boolean;
    is_automatic_day_off?: boolean;
}

export interface EmployeeAttendanceHistoryPayload {
    employee_id: number;
    employee_name: string;
    records_count: number;
    records: EmployeeAttendanceRecord[];
}

export interface WorkSchedule {
    start_time: string;
}

export interface InvoiceCleanupStats {
    completed_invoices_count: number;
    cancelled_invoices_count: number;
}
