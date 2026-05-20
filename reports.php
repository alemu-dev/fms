<?php
// ============================================================
//  reports.php  –  Analytics & Summary Reports
// ============================================================
require_once 'config/db.php';
require_once 'config/auth.php';
requireRole('admin', 'manager');
 
$pdo  = getDB();
$user = currentUser();
 
// ── Date range filter ──────────────────────────────────────
$rangeOptions = [
    '7'   => 'Last 7 Days',
    '30'  => 'Last 30 Days',
    '90'  => 'Last 90 Days',
    '365' => 'Last 12 Months',
    'all' => 'All Time',
];
$range     = $_GET['range'] ?? '30';
$rangeDays = is_numeric($range) ? (int)$range : null;
$dateClause = $rangeDays
    ? "AND f.created_at >= DATE_SUB(NOW(), INTERVAL $rangeDays DAY)"
    : '';
 
// ── Summary Stats ──────────────────────────────────────────
$total        = $pdo->query("SELECT COUNT(*) FROM files f WHERE 1=1 $dateClause")->fetchColumn();
$open         = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.status='open' $dateClause")->fetchColumn();
$inReview     = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.status='in_review' $dateClause")->fetchColumn();
$approved     = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.status='approved' $dateClause")->fetchColumn();
$rejected     = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.status='rejected' $dateClause")->fetchColumn();
$closed       = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.status='closed' $dateClause")->fetchColumn();
$urgent       = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.priority='urgent' $dateClause")->fetchColumn();
$overdue      = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.due_date < CURDATE() AND f.status NOT IN ('closed','archived','approved') $dateClause")->fetchColumn();
$confidential = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.confidential=1 $dateClause")->fetchColumn();
$unassigned   = $pdo->query("SELECT COUNT(*) FROM files f WHERE f.assigned_to IS NULL AND f.status NOT IN ('closed','archived') $dateClause")->fetchColumn();
 
// ── Files by Status ────────────────────────────────────────
$byStatus = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM files f WHERE 1=1 $dateClause
    GROUP BY status ORDER BY cnt DESC
")->fetchAll();
 
// ── Files by Priority ─────────────────────────────────────
$byPriority = $pdo->query("
    SELECT priority, COUNT(*) AS cnt
    FROM files f WHERE 1=1 $dateClause
    GROUP BY priority ORDER BY FIELD(priority,'urgent','high','normal','low')
")->fetchAll();
 
// ── Files by Department ───────────────────────────────────
$byDept = $pdo->query("
    SELECT d.name AS dept_name, COUNT(f.id) AS cnt,
           SUM(CASE WHEN f.status='open' THEN 1 ELSE 0 END) AS open_cnt,
           SUM(CASE WHEN f.priority='urgent' THEN 1 ELSE 0 END) AS urgent_cnt
    FROM departments d
    LEFT JOIN files f ON f.department_id = d.id
    " . ($dateClause ? "AND " . ltrim($dateClause, 'AND ') : "") . "
    GROUP BY d.id, d.name ORDER BY cnt DESC
")->fetchAll();
 
// ── Files by Category ─────────────────────────────────────
$byCat = $pdo->query("
    SELECT c.name AS cat_name, COUNT(f.id) AS cnt
    FROM categories c
    LEFT JOIN files f ON f.category_id = c.id
    " . ($dateClause ? "AND " . ltrim($dateClause, 'AND ') : "") . "
    GROUP BY c.id, c.name ORDER BY cnt DESC
")->fetchAll();
 
// ── Monthly Trend (last 12 months always) ─────────────────
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label,
           DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           COUNT(*) AS cnt
    FROM files
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
")->fetchAll();
 
// ── Top Staff by Files Created ────────────────────────────
$topCreators = $pdo->query("
    SELECT u.full_name, u.role, COUNT(f.id) AS cnt
    FROM users u
    LEFT JOIN files f ON f.created_by = u.id
    " . ($dateClause ? "AND " . ltrim($dateClause, 'AND ') : "") . "
    WHERE u.is_active = 1
    GROUP BY u.id, u.full_name, u.role
    ORDER BY cnt DESC LIMIT 8
")->fetchAll();
 
// ── Top Staff by Files Assigned ───────────────────────────
$topAssigned = $pdo->query("
    SELECT u.full_name, u.role,
           COUNT(f.id) AS total,
           SUM(CASE WHEN f.status NOT IN ('closed','archived','approved') THEN 1 ELSE 0 END) AS pending
    FROM users u
    LEFT JOIN files f ON f.assigned_to = u.id
    " . ($dateClause ? "AND " . ltrim($dateClause, 'AND ') : "") . "
    WHERE u.is_active = 1
    GROUP BY u.id, u.full_name, u.role
    ORDER BY total DESC LIMIT 8
")->fetchAll();
 
// ── Recent Activity ───────────────────────────────────────
$recentActivity = $pdo->query("
    SELECT al.action, al.created_at, u.full_name, al.details
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC LIMIT 10
")->fetchAll();
 
// ── Overdue Files List ────────────────────────────────────
$overdueFiles = $pdo->query("
    SELECT f.file_number, f.title, f.due_date, f.priority,
           d.name AS dept_name, u.full_name AS assignee
    FROM files f
    LEFT JOIN departments d ON f.department_id = d.id
    LEFT JOIN users u ON f.assigned_to = u.id
    WHERE f.due_date < CURDATE()
      AND f.status NOT IN ('closed','archived','approved')
    ORDER BY f.due_date ASC LIMIT 10
")->fetchAll();
 
// JSON for charts
$monthlyJson  = json_encode(array_map(fn($r) => ['label' => $r['month_label'], 'cnt' => (int)$r['cnt']], $monthly));
$statusJson   = json_encode(array_map(fn($r) => ['label' => $r['status'],   'cnt' => (int)$r['cnt']], $byStatus));
$priorityJson = json_encode(array_map(fn($r) => ['label' => $r['priority'], 'cnt' => (int)$r['cnt']], $byPriority));
$deptJson     = json_encode(array_map(fn($r) => ['label' => $r['dept_name'], 'cnt' => (int)$r['cnt']], $byDept));
 
include 'includes/header.php';
?>
 
<style>
/* ── Report-specific styles ── */
.report-range-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.report-range-bar span {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin-right: 4px;
}
.range-btn {
    padding: 5px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-secondary);
    font-size: 0.78rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    font-family: var(--font-mono);
}
.range-btn:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
.range-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
 
.report-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
.report-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
.report-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 20px 22px;
}
.report-card h3 {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}
.report-card.span-2 { grid-column: span 2; }
.report-card.span-3 { grid-column: span 3; }
 
/* Stats row */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 16px 18px;
    position: relative;
    overflow: hidden;
}
.kpi-card::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    background: var(--accent);
    opacity: 0.4;
}
.kpi-card.kpi-red::after   { background: var(--red); }
.kpi-card.kpi-green::after { background: var(--green); }
.kpi-card.kpi-yellow::after{ background: var(--yellow); }
.kpi-card.kpi-orange::after{ background: var(--orange); }
.kpi-val {
    font-size: 1.8rem;
    font-weight: 700;
    font-family: var(--font-mono);
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 4px;
}
.kpi-card.kpi-red    .kpi-val { color: var(--red); }
.kpi-card.kpi-yellow .kpi-val { color: var(--yellow); }
.kpi-card.kpi-orange .kpi-val { color: var(--orange); }
.kpi-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted);
}
 
/* Chart containers */
canvas { display: block; width: 100% !important; }
 
/* Bar chart (pure CSS) */
.bar-list { display: flex; flex-direction: column; gap: 10px; }
.bar-item  { display: flex; flex-direction: column; gap: 4px; }
.bar-label-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.78rem;
    color: var(--text-secondary);
}
.bar-label-row strong { color: var(--text-primary); font-family: var(--font-mono); font-size: 0.8rem; }
.bar-track {
    height: 7px;
    background: var(--bg-surface);
    border-radius: 4px;
    overflow: hidden;
}
.bar-fill {
    height: 100%;
    border-radius: 4px;
    background: var(--accent);
    transition: width 0.6s ease;
}
.bar-fill.green  { background: var(--green); }
.bar-fill.yellow { background: var(--yellow); }
.bar-fill.red    { background: var(--red); }
.bar-fill.orange { background: var(--orange); }
.bar-fill.muted  { background: var(--text-muted); }
 
/* Status dot legend */
.legend-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-secondary); }
.legend-dot  { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
 
/* Activity log */
.activity-list { display: flex; flex-direction: column; gap: 0; }
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 0.8rem;
}
.activity-item:last-child { border-bottom: none; }
.activity-action {
    font-family: var(--font-mono);
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 3px;
    background: var(--bg-surface);
    color: var(--accent);
    white-space: nowrap;
    flex-shrink: 0;
}
.activity-meta { color: var(--text-secondary); flex: 1; }
.activity-meta strong { color: var(--text-primary); }
.activity-time { font-size: 0.7rem; color: var(--text-muted); white-space: nowrap; }
 
/* Overdue table */
.overdue-tag {
    font-size: 0.68rem;
    font-family: var(--font-mono);
    color: var(--red);
    background: rgba(239,68,68,.1);
    padding: 1px 7px;
    border-radius: 3px;
}
 
/* Donut chart placeholder using conic-gradient */
.donut-wrap { display: flex; align-items: center; gap: 20px; }
.donut-legend { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.donut-legend-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.8rem;
}
.donut-legend-label { display: flex; align-items: center; gap: 8px; color: var(--text-secondary); }
.donut-legend-val   { font-family: var(--font-mono); color: var(--text-primary); font-weight: 600; }
.donut-swatch { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
 
@media (max-width: 1100px) {
    .kpi-row       { grid-template-columns: repeat(3, 1fr); }
    .report-grid   { grid-template-columns: 1fr; }
    .report-grid-3 { grid-template-columns: 1fr; }
    .report-card.span-2,
    .report-card.span-3 { grid-column: span 1; }
}
@media (max-width: 700px) {
    .kpi-row { grid-template-columns: repeat(2, 1fr); }
}
</style>
 
<div class="page-header">
    <h1>Reports &amp; Analytics</h1>
    <a href="dashboard.php" class="btn btn-outline">← Dashboard</a>
</div>
 
<!-- Date Range Selector -->
<div class="report-range-bar">
    <span>Range:</span>
    <?php foreach ($rangeOptions as $key => $label): ?>
        <a href="?range=<?= $key ?>" class="range-btn <?= $range === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>
 
<!-- KPI Row -->
<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-val"><?= number_format($total) ?></div>
        <div class="kpi-label">Total Files</div>
    </div>
    <div class="kpi-card kpi-green">
        <div class="kpi-val"><?= number_format($approved) ?></div>
        <div class="kpi-label">Approved</div>
    </div>
    <div class="kpi-card kpi-yellow">
        <div class="kpi-val"><?= number_format($inReview) ?></div>
        <div class="kpi-label">In Review</div>
    </div>
    <div class="kpi-card kpi-red">
        <div class="kpi-val"><?= number_format($overdue) ?></div>
        <div class="kpi-label">Overdue</div>
    </div>
    <div class="kpi-card kpi-orange">
        <div class="kpi-val"><?= number_format($urgent) ?></div>
        <div class="kpi-label">Urgent</div>
    </div>
</div>
 
<!-- Row 1: Monthly Trend (full width) -->
<div class="report-card" style="margin-bottom:20px;">
    <h3>Monthly File Registrations — Last 12 Months</h3>
    <canvas id="chartMonthly" height="80"></canvas>
</div>
 
<!-- Row 2: Status + Priority -->
<div class="report-grid" style="margin-bottom:20px;">
    <div class="report-card">
        <h3>Files by Status</h3>
        <?php
        $statusColors = ['open'=>'green','in_review'=>'yellow','approved'=>'','rejected'=>'red','archived'=>'muted','closed'=>'muted'];
        $maxS = max(1, max(array_column($byStatus, 'cnt')));
        ?>
        <div class="bar-list">
        <?php foreach ($byStatus as $row): ?>
            <div class="bar-item">
                <div class="bar-label-row">
                    <span><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span>
                    <strong><?= $row['cnt'] ?></strong>
                </div>
                <div class="bar-track">
                    <div class="bar-fill <?= $statusColors[$row['status']] ?? '' ?>"
                         style="width:<?= round($row['cnt']/$maxS*100) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
 
    <div class="report-card">
        <h3>Files by Priority</h3>
        <?php
        $prioColors = ['urgent'=>'red','high'=>'orange','normal'=>'','low'=>'muted'];
        $maxP = max(1, max(array_column($byPriority, 'cnt')));
        ?>
        <div class="bar-list">
        <?php foreach ($byPriority as $row): ?>
            <div class="bar-item">
                <div class="bar-label-row">
                    <span><?= ucfirst($row['priority']) ?></span>
                    <strong><?= $row['cnt'] ?></strong>
                </div>
                <div class="bar-track">
                    <div class="bar-fill <?= $prioColors[$row['priority']] ?? '' ?>"
                         style="width:<?= round($row['cnt']/$maxP*100) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
 
<!-- Row 3: By Department -->
<div class="report-card" style="margin-bottom:20px;">
    <h3>Files by Department</h3>
    <?php $maxD = max(1, max(array_column($byDept, 'cnt'))); ?>
    <div class="bar-list">
    <?php foreach ($byDept as $row): ?>
        <div class="bar-item">
            <div class="bar-label-row">
                <span><?= htmlspecialchars($row['dept_name']) ?>
                    <?php if ($row['urgent_cnt'] > 0): ?>
                        <span style="color:var(--red);font-size:0.68rem;margin-left:6px;">
                            ▲ <?= $row['urgent_cnt'] ?> urgent
                        </span>
                    <?php endif; ?>
                </span>
                <strong><?= $row['cnt'] ?> &nbsp;<span style="color:var(--text-muted);font-weight:400;font-size:0.72rem;">(<?= $row['open_cnt'] ?> open)</span></strong>
            </div>
            <div class="bar-track">
                <div class="bar-fill" style="width:<?= round($row['cnt']/$maxD*100) ?>%"></div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
 
<!-- Row 4: By Category + Staff Created -->
<div class="report-grid" style="margin-bottom:20px;">
    <div class="report-card">
        <h3>Files by Category</h3>
        <?php $maxC = max(1, max(array_column($byCat, 'cnt'))); ?>
        <div class="bar-list">
        <?php foreach ($byCat as $row): ?>
            <div class="bar-item">
                <div class="bar-label-row">
                    <span><?= htmlspecialchars($row['cat_name']) ?></span>
                    <strong><?= $row['cnt'] ?></strong>
                </div>
                <div class="bar-track">
                    <div class="bar-fill" style="width:<?= round($row['cnt']/$maxC*100) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
 
    <div class="report-card">
        <h3>Top Staff — Files Created</h3>
        <?php $maxU = max(1, max(array_column($topCreators, 'cnt') ?: [1])); ?>
        <div class="bar-list">
        <?php foreach ($topCreators as $row): ?>
            <div class="bar-item">
                <div class="bar-label-row">
                    <span><?= htmlspecialchars($row['full_name']) ?>
                        <span style="font-size:0.65rem;color:var(--text-muted);margin-left:4px;"><?= strtoupper($row['role']) ?></span>
                    </span>
                    <strong><?= $row['cnt'] ?></strong>
                </div>
                <div class="bar-track">
                    <div class="bar-fill" style="width:<?= round($row['cnt']/$maxU*100) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
 
<!-- Row 5: Workload + Overdue -->
<div class="report-grid" style="margin-bottom:20px;">
    <div class="report-card">
        <h3>Staff Workload — Assigned Files</h3>
        <div class="data-table" style="font-size:0.8rem;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);">
                        <th style="padding:8px 6px;text-align:left;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);">Staff Member</th>
                        <th style="padding:8px 6px;text-align:right;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);">Total</th>
                        <th style="padding:8px 6px;text-align:right;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);">Pending</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topAssigned as $row): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:8px 6px;color:var(--text-primary);">
                        <?= htmlspecialchars($row['full_name']) ?>
                        <span style="font-size:0.65rem;color:var(--text-muted);margin-left:4px;"><?= strtoupper($row['role']) ?></span>
                    </td>
                    <td style="padding:8px 6px;text-align:right;font-family:var(--font-mono);color:var(--text-primary);"><?= $row['total'] ?></td>
                    <td style="padding:8px 6px;text-align:right;font-family:var(--font-mono);color:<?= $row['pending'] > 5 ? 'var(--yellow)' : 'var(--text-secondary)' ?>;"><?= $row['pending'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
 
    <div class="report-card">
        <h3>Overdue Files <span style="color:var(--red);">(<?= $overdue ?>)</span></h3>
        <?php if (empty($overdueFiles)): ?>
            <p style="color:var(--text-muted);font-size:0.85rem;">No overdue files. ✓</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($overdueFiles as $f): ?>
            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <div>
                        <code style="font-size:0.72rem;"><?= htmlspecialchars($f['file_number']) ?></code>
                        <div style="font-size:0.82rem;color:var(--text-primary);margin-top:2px;"><?= htmlspecialchars(mb_strimwidth($f['title'],0,40,'…')) ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;">
                            <?= htmlspecialchars($f['dept_name'] ?? '—') ?> &middot;
                            <?= htmlspecialchars($f['assignee'] ?? 'Unassigned') ?>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <span class="overdue-tag">OVERDUE</span>
                        <div style="font-size:0.7rem;color:var(--red);margin-top:3px;"><?= date('d M Y', strtotime($f['due_date'])) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
 
<!-- Row 6: Quick stats + Recent Activity -->
<div class="report-grid" style="margin-bottom:20px;">
    <div class="report-card">
        <h3>Additional Metrics</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <?php
            $extras = [
                ['Confidential Files', $confidential, 'var(--red)'],
                ['Unassigned (Active)', $unassigned, 'var(--yellow)'],
                ['Rejected', $rejected, 'var(--red)'],
                ['Closed / Archived', $closed + $pdo->query("SELECT COUNT(*) FROM files f WHERE f.status='archived' $dateClause")->fetchColumn(), 'var(--text-muted)'],
            ];
            ?>
            <?php foreach ($extras as [$label, $val, $color]): ?>
            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;">
                <div style="font-size:1.4rem;font-weight:700;font-family:var(--font-mono);color:<?= $color ?>;"><?= number_format($val) ?></div>
                <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-top:3px;"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
 
    <div class="report-card">
        <h3>Recent Activity</h3>
        <div class="activity-list">
        <?php foreach ($recentActivity as $a): ?>
            <div class="activity-item">
                <span class="activity-action"><?= htmlspecialchars($a['action']) ?></span>
                <span class="activity-meta">
                    <strong><?= htmlspecialchars($a['full_name'] ?? 'System') ?></strong>
                    <?php if ($a['details']): ?>
                        — <?= htmlspecialchars(mb_strimwidth($a['details'], 0, 45, '…')) ?>
                    <?php endif; ?>
                </span>
                <span class="activity-time"><?= date('d M, H:i', strtotime($a['created_at'])) ?></span>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
 
<!-- Chart.js for the monthly trend line -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const monthly  = <?= $monthlyJson ?>;
 
    // ── Monthly bar chart ──
    const mCtx = document.getElementById('chartMonthly').getContext('2d');
    new Chart(mCtx, {
        type: 'bar',
        data: {
            labels: monthly.map(d => d.label),
            datasets: [{
                label: 'Files Registered',
                data: monthly.map(d => d.cnt),
                backgroundColor: 'rgba(14,165,233,0.25)',
                borderColor: '#0ea5e9',
                borderWidth: 1.5,
                borderRadius: 3,
                hoverBackgroundColor: 'rgba(14,165,233,0.45)',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0e1318',
                    borderColor: '#1e2d3d',
                    borderWidth: 1,
                    titleColor: '#d4e3f0',
                    bodyColor: '#6e8ca8',
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y} files registered`
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: '#1e2d3d' },
                    ticks: { color: '#3d5570', font: { family: 'IBM Plex Mono', size: 11 } }
                },
                y: {
                    grid: { color: '#1e2d3d' },
                    ticks: { color: '#3d5570', font: { family: 'IBM Plex Mono', size: 11 }, precision: 0 },
                    beginAtZero: true
                }
            }
        }
    });
})();
</script>
 
<?php include 'includes/footer.php'; ?>