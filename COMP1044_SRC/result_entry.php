<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_login();

$pdo = db();
$user = current_user();

$criteria = [
    'undertaking_tasks' => ['label' => 'Undertaking Tasks/Projects', 'weight' => 10],
    'health_safety' => ['label' => 'Health and Safety Requirements at the Workplace', 'weight' => 10],
    'theoretical_knowledge' => ['label' => 'Connectivity and Use of Theoretical Knowledge', 'weight' => 10],
    'written_report' => ['label' => 'Presentation of the Report as a Written Document', 'weight' => 15],
    'language_clarity' => ['label' => 'Clarity of Language and Illustration', 'weight' => 10],
    'lifelong_learning' => ['label' => 'Lifelong Learning Activities', 'weight' => 15],
    'project_management' => ['label' => 'Project Management', 'weight' => 15],
    'time_management' => ['label' => 'Time Management', 'weight' => 15],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!is_assessor()) {
        flash('error', 'Only assessor accounts are allowed to submit assessment marks.');
        redirect('result_entry.php');
    }

    $studentId = trim($_POST['student_id'] ?? '');
    $comments = trim($_POST['comments'] ?? '');

    $internshipSql = 'SELECT i.internship_id, s.student_id, s.student_name
        FROM internships i
        JOIN students s ON s.student_id = i.student_id
        WHERE s.student_id = :student_id';
    if (is_assessor()) {
        $internshipSql .= ' AND i.assessor_id = :assessor_id';
    }

    $stmt = $pdo->prepare($internshipSql);
    $params = ['student_id' => $studentId];
    if (is_assessor()) {
        $params['assessor_id'] = $user['user_id'];
    }
    $stmt->execute($params);
    $internship = $stmt->fetch();

    if (!$internship) {
        flash('error', 'Student not found or not assigned to the logged-in assessor.');
        redirect('result_entry.php');
    }

    $scores = [];
    foreach (array_keys($criteria) as $field) {
        $value = (float) ($_POST[$field] ?? -1);
        if ($value < 0 || $value > 100) {
            flash('error', 'All marks must be between 0 and 100.');
            redirect('result_entry.php?student_id=' . urlencode($studentId));
        }
        $scores[$field] = $value;
    }

    $existingStmt = $pdo->prepare('SELECT assessment_id FROM assessments WHERE internship_id = :internship_id');
    $existingStmt->execute(['internship_id' => $internship['internship_id']]);
    $existing = $existingStmt->fetch();

    if ($existing) {
        $sql = 'UPDATE assessments SET
            undertaking_tasks = :undertaking_tasks,
            health_safety = :health_safety,
            theoretical_knowledge = :theoretical_knowledge,
            written_report = :written_report,
            language_clarity = :language_clarity,
            lifelong_learning = :lifelong_learning,
            project_management = :project_management,
            time_management = :time_management,
            comments = :comments,
            assessed_at = CURRENT_TIMESTAMP
            WHERE internship_id = :internship_id';
    } else {
        $sql = 'INSERT INTO assessments (
            internship_id, undertaking_tasks, health_safety, theoretical_knowledge, written_report,
            language_clarity, lifelong_learning, project_management, time_management, comments
        ) VALUES (
            :internship_id, :undertaking_tasks, :health_safety, :theoretical_knowledge, :written_report,
            :language_clarity, :lifelong_learning, :project_management, :time_management, :comments
        )';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($scores + [
        'internship_id' => $internship['internship_id'],
        'comments' => $comments ?: null,
    ]);

    flash('success', 'Assessment saved successfully.');
    redirect('result_entry.php?student_id=' . urlencode($studentId));
}

$studentSql = 'SELECT s.student_id, s.student_name, s.programme, s.company_name, i.internship_id, i.assessor_id, u.full_name AS assessor_name
    FROM students s
    JOIN internships i ON i.student_id = s.student_id
    JOIN users u ON u.user_id = i.assessor_id';
if (is_assessor()) {
    $studentSql .= ' WHERE i.assessor_id = :assessor_id';
}
$studentSql .= ' ORDER BY s.student_id';

$stmt = $pdo->prepare($studentSql);
$params = [];
if (is_assessor()) {
    $params['assessor_id'] = $user['user_id'];
}
$stmt->execute($params);
$students = $stmt->fetchAll();

$selectedStudentId = trim($_GET['student_id'] ?? ($students[0]['student_id'] ?? ''));
$selectedStudent = null;
foreach ($students as $student) {
    if ($student['student_id'] === $selectedStudentId) {
        $selectedStudent = $student;
        break;
    }
}

$assessment = null;
if ($selectedStudent) {
    $stmt = $pdo->prepare('SELECT * FROM assessments WHERE internship_id = :internship_id');
    $stmt->execute(['internship_id' => $selectedStudent['internship_id']]);
    $assessment = $stmt->fetch() ?: null;
}

function score_value(?array $assessment, string $field): string
{
    return $assessment[$field] ?? '';
}

function calculate_grade(?float $mark): string
{
    if ($mark === null) {
        return 'Not calculated';
    }
    if ($mark >= 80) {
        return 'Excellent';
    }
    if ($mark >= 70) {
        return 'Very Good';
    }
    if ($mark >= 60) {
        return 'Good';
    }
    if ($mark >= 50) {
        return 'Pass';
    }
    return 'Needs Improvement';
}

$finalMark = $assessment ? (float) $assessment['final_mark'] : null;

render_header('Assessment Entry');
?>
<section class="two-column">
    <article class="panel">
        <h2>Assessment Form</h2>
        <?php if (!$students): ?>
            <div class="alert alert-error">No students are assigned to this assessor yet.</div>
        <?php else: ?>
            <form method="get" class="form-grid" style="margin-bottom: 18px;">
                <div>
                    <label for="student_id">Select Student</label>
                    <select id="student_id" name="student_id" onchange="this.form.submit()">
                        <?php foreach ($students as $student): ?>
                            <option value="<?= h($student['student_id']) ?>" <?= $selectedStudentId === $student['student_id'] ? 'selected' : '' ?>>
                                <?= h($student['student_id'] . ' - ' . $student['student_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($selectedStudent): ?>
                <div class="student-card">
                    <strong><?= h($selectedStudent['student_name']) ?></strong><br />
                    Student ID: <?= h($selectedStudent['student_id']) ?><br />
                    Programme: <?= h($selectedStudent['programme']) ?><br />
                    Internship Company: <?= h($selectedStudent['company_name']) ?><br />
                    Assessor: <?= h($selectedStudent['assessor_name']) ?>
                </div>

                <form method="post" class="form-grid">
                    <?= csrf_field() ?>
                    <input type="hidden" name="student_id" value="<?= h($selectedStudent['student_id']) ?>" />
                    <div class="score-grid">
                        <?php foreach ($criteria as $field => $criterion): ?>
                            <div class="score-card">
                                <label for="<?= h($field) ?>"><?= h($criterion['label']) ?></label>
                                <input
                                    id="<?= h($field) ?>"
                                    name="<?= h($field) ?>"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="<?= h(score_value($assessment, $field)) ?>"
                                    required
                                />
                                <div class="helper">Weightage: <?= h((string) $criterion['weight']) ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <label for="comments">Comments</label>
                        <textarea id="comments" name="comments"><?= h($assessment['comments'] ?? '') ?></textarea>
                    </div>
                    <div class="button-row">
                        <?php if (is_assessor()): ?>
                            <button type="submit">Save Assessment</button>
                        <?php endif; ?>
                        <a class="button-link ghost" href="result_entry.php?student_id=<?= h($selectedStudent['student_id']) ?>">Reset</a>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </article>

    <aside class="panel">
        <h2>Assessment Summary</h2>
        <?php if ($assessment): ?>
            <div class="summary-box">
                <strong>Final Mark</strong>
                <div class="mark"><?= number_format($finalMark, 2) ?></div>
                <div class="helper">Grade band: <?= h(calculate_grade($finalMark)) ?></div>
            </div>
            <div class="summary-box" style="margin-top: 16px;">
                <strong>Weighted Breakdown</strong>
                <?php foreach ($criteria as $field => $criterion): ?>
                    <?php $value = (float) $assessment[$field]; ?>
                    <div><?= h($criterion['label']) ?>: <?= number_format($value, 2) ?> x <?= h((string) $criterion['weight']) ?>% = <?= number_format($value * ($criterion['weight'] / 100), 2) ?></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">Select a student and save marks to view the calculated summary.</div>
        <?php endif; ?>
    </aside>
</section>
<?php render_footer(); ?>
