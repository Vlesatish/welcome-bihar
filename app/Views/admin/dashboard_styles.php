<style>
/* Executive KPI Cards with Ambient Glow */
.dashboard-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    user-select: none;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.04), 0 1px 3px -1px rgba(15, 23, 42, 0.02);
}
.dashboard-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.08), 0 4px 8px -2px rgba(15, 23, 42, 0.04);
}
.dashboard-stat-card::after {
    content: '';
    position: absolute;
    top: -20px;
    right: -20px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    filter: blur(28px);
    opacity: 0.22;
    pointer-events: none;
}
.dashboard-stat-card.card-pending {
    background: linear-gradient(135deg, #ffffff 0%, #fffdf8 100%);
    border-left: 4px solid #f59e0b;
}
.dashboard-stat-card.card-pending::after {
    background: #f59e0b;
}
.dashboard-stat-card.card-in-process {
    background: linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
    border-left: 4px solid #0284c7;
}
.dashboard-stat-card.card-in-process::after {
    background: #0284c7;
}
.dashboard-stat-card.card-approved {
    background: linear-gradient(135deg, #ffffff 0%, #f6fdf9 100%);
    border-left: 4px solid #10b981;
}
.dashboard-stat-card.card-approved::after {
    background: #10b981;
}
.dashboard-stat-card.card-rejected {
    background: linear-gradient(135deg, #ffffff 0%, #fff8f8 100%);
    border-left: 4px solid #ef4444;
}
.dashboard-stat-card.card-rejected::after {
    background: #ef4444;
}

.dashboard-stat-card.active-card {
    box-shadow: 0 0 0 2px #3b82f6, 0 12px 28px rgba(59, 130, 246, 0.18) !important;
}
.dashboard-stat-card.card-pending.active-card {
    box-shadow: 0 0 0 2px #f59e0b, 0 12px 28px rgba(245, 158, 11, 0.2) !important;
}
.dashboard-stat-card.card-in-process.active-card {
    box-shadow: 0 0 0 2px #0284c7, 0 12px 28px rgba(2, 132, 199, 0.2) !important;
}
.dashboard-stat-card.card-approved.active-card {
    box-shadow: 0 0 0 2px #10b981, 0 12px 28px rgba(16, 185, 129, 0.2) !important;
}
.dashboard-stat-card.card-rejected.active-card {
    box-shadow: 0 0 0 2px #ef4444, 0 12px 28px rgba(239, 68, 68, 0.2) !important;
}

.kpi-icon-pill {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
.stat-card-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.stat-card-count {
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 0.2rem;
    letter-spacing: -0.02em;
}
.stat-card-sub {
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748b;
    margin-top: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

/* Pulsing dot */
.pulse-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background-color: #f59e0b;
    box-shadow: 0 0 0 rgba(245, 158, 11, 0.5);
    animation: pulse-dot-anim 1.6s infinite;
}
@keyframes pulse-dot-anim {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

/* Spin Icon */
.spin-icon {
    display: inline-block;
    animation: spin-anim 2s linear infinite;
}
@keyframes spin-anim {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Status Badges & Pills */
.status-pill {
    font-size: 0.72rem;
    letter-spacing: 0.3px;
    border-radius: 20px;
    padding: 0.28rem 0.65rem !important;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}
.badge-status-pending { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.badge-status-in-process { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.badge-status-approved { background-color: #d1fae5; color: #047857; border: 1px solid #a7f3d0; }
.badge-status-rejected { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

/* Applicant Avatar */
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #4338ca;
    font-weight: 700;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.btn-xs {
    padding: 0.22rem 0.55rem;
    font-size: 0.72rem;
    line-height: 1.25;
}
.font-monospace {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace !important;
}
.nowrap-cell {
    white-space: nowrap !important;
}

/* More Details Button */
.btn-more-details {
    font-size: 0.72rem !important;
    color: #b45309 !important;
    border-color: #fde68a !important;
    background: #fffdf7 !important;
    transition: all 0.15s ease-in-out;
}
.btn-more-details:hover {
    background: #fef3c7 !important;
    color: #92400e !important;
    border-color: #f59e0b !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);
}

/* Chat icon button */
.btn-chat-icon:hover {
    background: #eff6ff !important;
    border-color: #93c5fd !important;
    color: #2563eb !important;
    transform: translateY(-1px);
}

.upload-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    background-color: #f8fafc;
    transition: all 0.2s ease;
    cursor: pointer;
}
.upload-dropzone:hover {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

/* Standalone Filters Card & Controls */
.filter-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    margin-bottom: 0.25rem;
    white-space: nowrap;
}
.admin-filters-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.65rem;
    width: 100%;
}
.filter-col-district {
    flex: 0 0 150px;
    width: 150px;
}
.filter-col-block {
    flex: 0 0 150px;
    width: 150px;
}
.filter-col-status {
    flex: 0 0 145px;
    width: 145px;
}
.filter-col-code {
    flex: 0 0 115px;
    width: 115px;
}
.filter-col-date {
    flex: 0 0 120px;
    width: 120px;
}
.filter-col-search {
    flex: 1 1 180px;
    min-width: 160px;
}
.filter-col-btn {
    flex: 0 0 auto;
}
.filter-ctrl, .filter-select, .filter-btn {
    height: 36px !important;
    font-size: 13.5px !important;
    border-radius: 6px !important;
}
.filter-ctrl:focus, .filter-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}
.filter-select {
    padding: 0.25rem 1.6rem 0.25rem 0.65rem !important;
    background-position: right 0.5rem center !important;
    font-weight: 500;
    color: #1e293b;
    border: 1px solid #cbd5e1;
    background-color: #ffffff;
}
.filter-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.85rem !important;
    font-weight: 600;
    white-space: nowrap;
}

@media (min-width: 1200px) {
    .admin-filters-grid {
        flex-wrap: nowrap !important;
    }
}

@media (max-width: 1199.98px) and (min-width: 768px) {
    .filter-col-district { flex: 1 1 140px; }
    .filter-col-block { flex: 1 1 140px; }
    .filter-col-status { flex: 1 1 135px; }
    .filter-col-code { flex: 1 1 115px; }
    .filter-col-date { flex: 1 1 115px; }
    .filter-col-search { flex: 1 1 180px; }
    .filter-col-btn { flex: 0 0 auto; }
}

@media (max-width: 767.98px) {
    .filter-col-district,
    .filter-col-block {
        flex: 1 1 calc(50% - 0.35rem);
        width: auto !important;
        min-width: 120px;
    }
    .filter-col-code,
    .filter-col-date {
        flex: 1 1 calc(50% - 0.35rem);
        width: auto !important;
        min-width: 110px;
    }
    .filter-col-search {
        flex: 1 1 100%;
        min-width: 100%;
    }
    .filter-col-btn {
        flex: 1 1 calc(50% - 0.35rem);
    }
}
</style>