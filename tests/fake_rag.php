<?php
declare(strict_types=1);

// Stand-in for the RAG service during tests. Same contract as
// hk-law-rag's POST /api/ask, so the PHP client is exercised end to end
// without a model, a vector store or network access.

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true);
$question = is_array($body) ? (string) ($body['question'] ?? '') : '';

if (str_contains($question, 'REFUSE_ME')) {
    echo json_encode([
        'question' => $question,
        'answer' => null,
        'refused' => true,
        'reason' => '檢索相似度不足，未呼叫模型',
    ]);
    exit;
}

echo json_encode([
    'question' => $question,
    'answer' => '已收到你的查詢，我們正在核對訂單紀錄，確認後會盡快回覆你處理方式。',
    'refused' => false,
], JSON_UNESCAPED_UNICODE);
