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

export interface Invoice {
    id: number;
    invoice_number: string;
    children_count: number;
    phone_number: string;
    visit_count: number;
    status: string;
    session_duration: SessionDuration;
    extensions: InvoiceExtension[];
    entered_at: string;
    expected_exit_at: string | null;
    exited_at: string | null;
    remaining_minutes: number | null;
    needs_parent_call: boolean;
    parent_call_marked_by: string | null;
    parent_call_marked_at: string | null;
}

export interface CustomerBonus {
    id: number;
    title: string;
    notes: string | null;
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

export interface PreviousVisitInvoice {
    id: number;
    invoice_number: string;
    children_count: number;
    original_duration_minutes: number;
    extensions_duration_minutes: number;
    total_duration_minutes: number;
    started_at: string | null;
    visit_date: string | null;
}

export interface PreviousVisitsPayload {
    phone_number: string;
    visit_count: number;
    average_children: number;
    invoices: PreviousVisitInvoice[];
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

export interface DashboardStats {
    active_invoices: number;
    active_children: number;
    completed_today: number;
    completed_month: number;
    total_customers: number;
}

export interface ChartSessionDuration {
    id: number;
    name: string;
    color: string;
    key: string;
}

export interface SessionDistributionItem {
    id: number;
    name: string;
    color: string;
    value: number;
    key: string;
}

export interface EmployeeAttendance {
    id: number;
    name: string;
    check_in: string;
    check_out: string;
    late_minutes: number | null;
    has_checked_in: boolean;
    has_checked_out: boolean;
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

export interface EmployeeAttendanceRecord {
    id: number;
    work_date: string;
    scheduled_start: string;
    checked_in_at: string;
    checked_out_at: string;
    late_label: string;
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
