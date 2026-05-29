<?php
// modules/feedback/inc/feedback_model.php
// Requires $pdo (PDO instance) from global config.

/**
 * Create a feedback prompt for an event (one per event).
 * Returns prompt ID or false if it already exists.
 */
function create_feedback_prompt_for_event(
    PDO $pdo,
    int $event_id,
    string $title = null,
    $open_at = null,
    $close_at = null
) {
    // Prevent duplicate prompts for the same event
    $stmt = $pdo->prepare("
        SELECT id 
        FROM feedback_prompts
        WHERE event_id = :eid
        LIMIT 1
    ");
    $stmt->execute([':eid' => $event_id]);

    if ($stmt->fetch()) {
        return false;
    }

    $title = $title ?? ('Feedback for event #' . $event_id);

    $ins = $pdo->prepare("
        INSERT INTO feedback_prompts (event_id, title, open_at, close_at)
        VALUES (:eid, :title, :open_at, :close_at)
    ");
    $ins->execute([
        ':eid'      => $event_id,
        ':title'    => $title,
        ':open_at'  => $open_at ?: null,
        ':close_at' => $close_at ?: null
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Get feedback prompt by event ID.
 */
function get_prompt_by_event(PDO $pdo, int $event_id) {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            e.title AS event_title,
            e.start_at,
            e.end_at
        FROM feedback_prompts p
        LEFT JOIN events e ON e.id = p.event_id
        WHERE p.event_id = :eid
        LIMIT 1
    ");
    $stmt->execute([':eid' => $event_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get feedback prompt by prompt ID.
 */
function get_prompt(PDO $pdo, int $prompt_id) {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            e.title AS event_title,
            e.start_at,
            e.end_at
        FROM feedback_prompts p
        LEFT JOIN events e ON e.id = p.event_id
        WHERE p.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $prompt_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all open feedback prompts for a user.
 * - Respects open_at / close_at window
 * - Removes prompts already answered by the user
 */
function get_open_prompts_for_user(PDO $pdo, ?int $user_id = null) {
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            e.title AS event_title,
            e.start_at,
            e.end_at
        FROM feedback_prompts p
        LEFT JOIN events e ON e.id = p.event_id
        WHERE (p.open_at IS NULL OR p.open_at <= :now)
          AND (p.close_at IS NULL OR p.close_at >= :now2)
        ORDER BY COALESCE(p.open_at, e.end_at, p.created_at) DESC
    ");
    $stmt->execute([
        ':now'  => $now,
        ':now2' => $now
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove prompts already answered by the user
    if ($user_id !== null) {
        foreach ($rows as $k => $r) {
            $q = $pdo->prepare("
                SELECT COUNT(*)
                FROM feedbacks
                WHERE prompt_id = :pid
                  AND user_id = :uid
                  AND deleted_at IS NULL
            ");
            $q->execute([
                ':pid' => $r['id'],
                ':uid' => $user_id
            ]);

            if ((int)$q->fetchColumn() > 0) {
                unset($rows[$k]);
            }
        }
    }

    return array_values($rows);
}

/**
 * Insert a feedback record.
 */
function insert_feedback(PDO $pdo, array $data) {
    $stmt = $pdo->prepare("
        INSERT INTO feedbacks
        (prompt_id, event_id, user_id, rating, comment, anonymous)
        VALUES (:pid, :eid, :uid, :rating, :comment, :anon)
    ");
    $stmt->execute([
        ':pid'     => $data['prompt_id'],
        ':eid'     => $data['event_id'] ?? null,
        ':uid'     => $data['user_id'] ?? null,
        ':rating'  => $data['rating'],
        ':comment' => $data['comment'] ?? null,
        ':anon'    => !empty($data['anonymous']) ? 1 : 0
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Get a single feedback by ID.
 */
function get_feedback_by_id(PDO $pdo, int $id) {
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            p.title AS prompt_title,
            e.title AS event_title,
            u.name AS user_name
        FROM feedbacks f
        LEFT JOIN feedback_prompts p ON p.id = f.prompt_id
        LEFT JOIN events e ON e.id = f.event_id
        LEFT JOIN users u ON u.id = f.user_id
        WHERE f.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all feedback submitted by a user.
 */
function get_feedbacks_for_user(PDO $pdo, int $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            p.title AS prompt_title,
            e.title AS event_title
        FROM feedbacks f
        LEFT JOIN feedback_prompts p ON p.id = f.prompt_id
        LEFT JOIN events e ON e.id = f.event_id
        WHERE f.user_id = :uid
          AND f.deleted_at IS NULL
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get images attached to a feedback.
 */
function get_feedback_images(PDO $pdo, int $feedback_id) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM feedback_images
        WHERE feedback_id = :fid
        ORDER BY id ASC
    ");
    $stmt->execute([':fid' => $feedback_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get feedback statistics for an event.
 */
function get_feedback_stats_for_event(PDO $pdo, int $event_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS cnt,
            AVG(rating) AS avg_rating
        FROM feedbacks
        WHERE event_id = :eid
          AND deleted_at IS NULL
    ");
    $stmt->execute([':eid' => $event_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
