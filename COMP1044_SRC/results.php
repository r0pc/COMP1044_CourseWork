<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_login();

$pdo = db();
$user = current_user();

$search = trim($_GET['search'] ?? '');

// Use the vw_student_assessment_summary view so the Result Viewing page pulls
// its rows directly from the database layer instead of re-joining four tables.
$sql = 'SELECT student_id, student_name, programme, company_name, assessor_name,
        undertaking_tasks, health_safety, theoretical_knowledge, written_report, language_clarity,
        lifelong_learning, project_management, time_management, final_mark, comments, assessed_at
    FROM vw_student_assessment_summary
    WHERE assessment_id IS NOT NULL';

$params = [];

if (is_assessor()) {
    $sql .= ' AND assessor_id = :assessor_id';
    $params['assessor_id'] = $user['user_id'];
}

if ($search !== '') {
    $sql .= ' AND (student_id LIKE :search OR student_name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

$sql .= ' ORDER BY student_id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

render_header('Result Viewing');
?>
<section class="panel">
    <h2>Internship Results</h2>
    <p class="helper">
        <?= is_admin()
            ? 'Admin can view results for all students.'
            : 'Assessors can view detailed mark breakdowns for the students assigned to them.' ?>
    </p>

    <form method="get" class="search-row" style="margin-bottom: 16px;">
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by student ID or student name" />
        <div></div>
        <div class="button-row">
            <button type="submit">Search</button>
            <a class="button-link ghost" href="results.php">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Student</th>
                <th>Assessor</th>
                <th>Mark Breakdown</th>
                <th>Final Result</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$results): ?>
                <tr><td colspan="4" class="empty">No results found for the selected filter.</td></tr>
            <?php else: ?>
                <?php foreach ($results as $result): ?>
                    <tr>
                        <td>
                            <strong><?= h($result['student_name']) ?></strong><br />
                            <?= h($result['student_id']) ?><br />
                            <span class="helper"><?= h($result['programme']) ?></span><br />
                            <span class="helper"><?= h($result['company_name']) ?></span>
                        </td>
                        <td><?= h($result['assessor_name']) ?></td>
                        <td>
                            Tasks: <?= number_format((float) $result['undertaking_tasks'], 2) ?><br />
                            Safety: <?= number_format((float) $result['health_safety'], 2) ?><br />
                            Theory: <?= number_format((float) $result['theoretical_knowledge'], 2) ?><br />
                            Report: <?= number_format((float) $result['written_report'], 2) ?><br />
                            Language: <?= number_format((float) $result['language_clarity'], 2) ?><br />
                            Learning: <?= number_format((float) $result['lifelong_learning'], 2) ?><br />
                            Project: <?= number_format((float) $result['project_management'], 2) ?><br />
                            Time: <?= number_format((float) $result['time_management'], 2) ?>
                        </td>
                        <td>
                            <span class="pill">Saved</span><br />
                            Final Mark: <?= number_format((float) $result['final_mark'], 2) ?><br />
                            <span class="helper"><?= h((string) $result['assessed_at']) ?></span><br />
                            <span class="helper"><?= h($result['comments'] ?: 'No comments provided.') ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
