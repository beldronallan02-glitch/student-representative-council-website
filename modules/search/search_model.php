<?php
// modules/search/search_model.php

function global_search(PDO $pdo, string $keyword) {
  $kw = trim($keyword);
  if ($kw === '') { return []; }
  $q = '%' . $kw . '%';
  $kwLower = function_exists('mb_strtolower') ? mb_strtolower($kw) : strtolower($kw);

  $results = [];

  // Helper: compute fuzzy relevance score
  $score = function(string $kw, string $title, string $text = ''): float {
    $kwL = function_exists('mb_strtolower') ? mb_strtolower($kw) : strtolower($kw);
    $titleL = function_exists('mb_strtolower') ? mb_strtolower($title) : strtolower($title);
    $textL = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);

    $s = 0.0;
    // substring boosts
    if ($kwL !== '' && strpos($titleL, $kwL) !== false) { $s += 3.0; }
    if ($kwL !== '' && strpos($textL, $kwL) !== false) { $s += 1.5; }

    // soundex boost (similar sounding)
    if (function_exists('soundex')) {
      $sdKw = soundex($kwL);
      if ($sdKw && $sdKw === soundex($titleL)) { $s += 1.2; }
    }

    // token-based Levenshtein similarity (best token)
    $tokens = preg_split('/\W+/u', $titleL . ' ' . $textL, -1, PREG_SPLIT_NO_EMPTY);
    $best = 0.0;
    foreach ($tokens as $t) {
      if ($t === '') { continue; }
      $len = function_exists('mb_strlen') ? mb_strlen($t) : strlen($t);
      $klen = function_exists('mb_strlen') ? mb_strlen($kwL) : strlen($kwL);
      if ($len === 0 || $klen === 0) { continue; }
      $dist = levenshtein($kwL, $t);
      $maxLen = max($len, $klen);
      $sim = 1.0 - min($dist, $maxLen) / $maxLen; // 0..1
      if ($sim > $best) { $best = $sim; }
    }
    $s += $best * 2.0; // weigh fuzzy similarity

    return $s;
  };

  // Announcements (LIKE + SOUNDEX + type keyword)
  try {
    $isType = in_array($kwLower, ['announcement','announcements'], true);
    $sql = $isType
      ? "SELECT id, title, excerpt, body, created_at FROM announcements WHERE status='published' ORDER BY created_at DESC LIMIT 200"
      : "SELECT id, title, excerpt, body, created_at FROM announcements WHERE status='published' AND (title LIKE :q OR excerpt LIKE :q OR body LIKE :q OR SOUNDEX(title)=SOUNDEX(:kw) OR SOUNDEX(excerpt)=SOUNDEX(:kw) OR SOUNDEX(body)=SOUNDEX(:kw)) ORDER BY created_at DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $isType ? $stmt->execute() : $stmt->execute([':q' => $q, ':kw' => $kw]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $results[] = [
        'type' => 'Announcement',
        'title' => $row['title'],
        'excerpt' => $row['excerpt'] ?? ($row['body'] ?? ''),
        'url' => '../announcements/announcement_view.php?id=' . (int)$row['id'],
        '_score' => $score($kw, (string)$row['title'], (string)($row['excerpt'] ?? $row['body'] ?? '')),
        '_date' => $row['created_at'] ?? null,
      ];
    }
  } catch (Throwable $e) {}

  // Events
  try {
    $isType = in_array($kwLower, ['event','events'], true);
    $sql = $isType
      ? "SELECT id, title, description, start_at FROM events WHERE status='published' ORDER BY start_at DESC LIMIT 200"
      : "SELECT id, title, description, start_at FROM events WHERE status='published' AND (title LIKE :q OR description LIKE :q OR SOUNDEX(title)=SOUNDEX(:kw) OR SOUNDEX(description)=SOUNDEX(:kw)) ORDER BY start_at DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $isType ? $stmt->execute() : $stmt->execute([':q' => $q, ':kw' => $kw]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $results[] = [
        'type' => 'Event',
        'title' => $row['title'],
        'excerpt' => $row['description'] ?? '',
        'url' => '../events/event.view.php?id=' . (int)$row['id'],
        '_score' => $score($kw, (string)$row['title'], (string)($row['description'] ?? '')),
        '_date' => $row['start_at'] ?? null,
      ];
    }
  } catch (Throwable $e) {}

  // Progress
  try {
    $isType = in_array($kwLower, ['progress','updates'], true);
    $sql = $isType
      ? "SELECT id, title, description, created_at FROM progress_entries WHERE status='published' ORDER BY created_at DESC LIMIT 200"
      : "SELECT id, title, description, created_at FROM progress_entries WHERE status='published' AND (title LIKE :q OR description LIKE :q OR SOUNDEX(title)=SOUNDEX(:kw) OR SOUNDEX(description)=SOUNDEX(:kw)) ORDER BY created_at DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $isType ? $stmt->execute() : $stmt->execute([':q' => $q, ':kw' => $kw]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $results[] = [
        'type' => 'Progress',
        'title' => $row['title'],
        'excerpt' => $row['description'] ?? '',
        'url' => '../progress/progress_view.php?id=' . (int)$row['id'],
        '_score' => $score($kw, (string)$row['title'], (string)($row['description'] ?? '')),
        '_date' => $row['created_at'] ?? null,
      ];
    }
  } catch (Throwable $e) {}

  // Sort by score desc, then by date desc
  usort($results, function($a, $b) {
    $sa = $a['_score'] ?? 0; $sb = $b['_score'] ?? 0;
    if ($sa === $sb) { return strcmp((string)($b['_date'] ?? ''), (string)($a['_date'] ?? '')); }
    return ($sb <=> $sa);
  });

  // Strip internal fields
  foreach ($results as &$r) { unset($r['_score'], $r['_date']); }

  return $results;
}
