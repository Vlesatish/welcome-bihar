<?php
namespace App\Services;

class TranslationService
{
    private static array $dictionary = [
        // App branding & navigation
        'app_name' => 'Welcome Bihar',
        'app_tagline' => 'Unified District & Block Citizen Services Data Portal',
        'dashboard' => 'Dashboard',
        'applications' => 'Applications',
        'new_application' => 'New Application',
        'application_list' => 'All Applications',
        'wallet' => 'Wallet',
        'wallet_balance' => 'Wallet Balance',
        'wallet_passbook' => 'Wallet Passbook',
        'topup_wallet' => 'Top Up Wallet',
        'reports' => 'Reports',
        'team_management' => 'Team & Scope',
        'data_types_manager' => 'Data Types Manager',
        'locations_manager' => 'Districts & Blocks',
        'users_manager' => 'Users & Operators',
        'payment_orders' => 'Payment Orders',
        'reconciliation' => 'Reconciliation',
        'system_settings' => 'System Settings',
        'audit_logs' => 'Audit Trail',
        'profile' => 'Profile',
        'logout' => 'Logout',
        'login' => 'Login',
        'register' => 'Register New Kiosk',
        'switch_language' => 'English',
        'current_language' => 'English',

        // Roles
        'role_admin' => 'Administrator',
        'role_distributor' => 'Distributor',
        'role_user' => 'Operator / User',

        // Application Statuses
        'status_pending' => 'Pending',
        'status_under_review' => 'Under Review',
        'status_approved' => 'Approved',
        'status_rejected' => 'Rejected',
        'status_pending_verification' => 'Pending Verification',
        'status_paid' => 'Paid',
        'status_failed' => 'Failed',
        'status_expired' => 'Expired',

        // Common Actions & Form elements
        'submit' => 'Submit Application',
        'save' => 'Save Changes',
        'cancel' => 'Cancel',
        'back' => 'Back',
        'next' => 'Next',
        'search' => 'Search',
        'filter' => 'Filter',
        'reset' => 'Reset',
        'export_csv' => 'Export CSV',
        'import_csv' => 'Import CSV',
        'actions' => 'Actions',
        'view_details' => 'View Details',
        'view_history' => 'Status History',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'rejection_reason' => 'Rejection Reason',
        'remarks' => 'Remarks / Notes',
        'required_field' => 'This field is required',
        'close' => 'Close',
        'confirm' => 'Confirm',

        // Application Wizard
        'step_1_location' => 'Step 1: Select District & Block',
        'step_2_service' => 'Step 2: Choose Data Type Service',
        'step_3_details' => 'Step 3: Fill Application Details',
        'select_district' => '-- Select District --',
        'select_block' => '-- Select Block --',
        'select_data_type' => '-- Select Data Type --',
        'district' => 'District',
        'block' => 'Block / Urban Body',
        'data_type' => 'Data Type',
        'app_reference' => 'Application Reference',
        'applicant_name' => 'Applicant Name',
        'applicant_mobile' => 'Mobile Number',
        'submitted_by' => 'Submitted By',
        'submitted_on' => 'Submission Date',
        'fee_amount' => 'Application Fee',
        'commission' => 'Distributor Commission',
        'duplicate_warning' => 'An application with this identifier already exists in system.',
        'insufficient_balance' => 'Insufficient wallet balance. Please top up your wallet.',
        'application_submitted_success' => 'Application submitted successfully! Reference number: ',
        'application_locked_notice' => 'This record is locked against unauthorized modifications.',

        // Wallet & Passbook
        'current_balance' => 'Current Available Balance',
        'total_credited' => 'Total Credited',
        'total_debited' => 'Total Debited',
        'txn_id' => 'Transaction ID',
        'txn_type' => 'Type',
        'direction' => 'Direction',
        'credit' => 'Credit (+)',
        'debit' => 'Debit (-)',
        'amount' => 'Amount',
        'prev_balance' => 'Previous Balance',
        'new_balance' => 'New Balance',
        'date_time' => 'Date & Time',
        'type_topup_credit' => 'Wallet Top-Up (UPI QR)',
        'type_application_fee_debit' => 'Application Submission Fee',
        'type_commission_credit' => 'Distributor Commission Earned',
        'type_refund_credit' => 'Application Rejection Refund',
        'type_admin_adjustment' => 'Admin Balance Adjustment',

        // ALL API UPI Payment Flow
        'topup_title' => 'Instant Wallet Recharge via UPI QR',
        'enter_amount' => 'Enter Top-Up Amount (INR)',
        'min_amount_note' => 'Minimum top-up amount: ₹50. Maximum: ₹25,000.',
        'generate_qr' => 'Generate UPI Payment QR',
        'scan_instructions' => 'Open Google Pay, PhonePe, Paytm, BHIM or any UPI App and scan this QR code to complete payment.',
        'order_reference' => 'Payment Order Reference',
        'merchant_notice' => 'UPI Scan & Pay is processed via merchant connected account through ALL API.',
        'awaiting_payment' => 'Awaiting payment confirmation... checking automatically...',
        'check_status_now' => 'Check Status Now',
        'payment_verified_credited' => 'Payment verified successfully! Wallet has been credited.',
        'payment_pending_review' => 'Payment is awaiting automated/manual confirmation. Please check back shortly.',
        'provider_disclaimer' => 'Note: ALL API provides UPI Scan & Pay QR generation and order status inquiry only. It is not a bank or fund settlement entity.',

        // Admin features
        'admin_adjustment' => 'Admin Manual Adjustment',
        'adjustment_reason_mandatory' => 'Adjustment reason is required and will be permanently audited.',
        'manage_data_types' => 'Configurable Data Types',
        'manage_fields' => 'Dynamic Fields Builder',
        'fee_configuration' => 'Fees & Commission Settings',
        'workflow_settings' => 'Workflow & Verification Rules',
        'provider_settings' => 'ALL API UPI Provider Settings',
        'distributor_review_toggle' => 'Require Distributor Review Before Admin Final Approval'
    ];

    public static function getLocale(): string
    {
        return 'en';
    }

    public static function setLocale(string $lang): void
    {
        $_SESSION['lang'] = 'en';
    }

    public static function trans(string $key, ?string $fallback = null): string
    {
        if (isset(self::$dictionary[$key])) {
            $val = self::$dictionary[$key];
            if (is_array($val)) {
                return $val['en'] ?? $fallback ?? $key;
            }
            return (string)$val;
        }

        return $fallback ?? $key;
    }
}

// Global translation helper
if (!function_exists('__')) {
    function __(string $key, ?string $fallback = null): string
    {
        return \App\Services\TranslationService::trans($key, $fallback);
    }
}

// Output escaper helper
if (!function_exists('e')) {
    function e(?string $string): string
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}
