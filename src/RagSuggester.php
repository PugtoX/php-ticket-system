<?php
declare(strict_types=1);

namespace App;

/**
 * Reply drafts from an HTTP RAG service (hk-law-rag's POST /api/ask).
 *
 * Network and parsing are one call because the only thing worth testing here
 * is how a payload turns into a draft: see interpret() and buildQuestion().
 */
final class RagSuggester implements Suggester
{
    public function __construct(
        private string $baseUrl,
        private int $timeoutSeconds = 20
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function suggest(string $subject, string $body): array
    {
        $payload = json_encode([
            'question' => self::buildQuestion($subject, $body),
            'top_k' => 3,
            'generate' => true,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->baseUrl . '/api/ask');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['reply' => null, 'error' => 'RAG 服務連不上：' . $err];
        }
        if ($code !== 200) {
            return ['reply' => null, 'error' => 'RAG 服務回 HTTP ' . $code];
        }

        return self::interpret(json_decode((string) $raw, true));
    }

    public static function buildQuestion(string $subject, string $body): string
    {
        $subject = trim($subject);
        $body = trim($body);

        return "你是香港網店的客服。以下是客戶的工單，請草擬一段可直接發給客人的回覆："
            . "先確認你理解他的問題，再說明下一步會怎樣處理。"
            . "不要承諾你做不到的退款或時限；資料不足時就說明會先核對再回覆。"
            . "用繁體中文，四句以內，不要加標題。\n\n"
            . "標題：{$subject}\n內容：{$body}";
    }

    /**
     * @param mixed $payload decoded JSON body from the RAG service
     * @return array{reply:?string,error:?string}
     */
    public static function interpret(mixed $payload): array
    {
        if (!is_array($payload)) {
            return ['reply' => null, 'error' => 'RAG 回應不是 JSON 物件'];
        }
        if (isset($payload['error']) && (string) $payload['error'] !== '') {
            return ['reply' => null, 'error' => 'RAG 內部錯誤：' . (string) $payload['error']];
        }
        if (!empty($payload['refused'])) {
            $why = (string) ($payload['reason'] ?? '檢索結果不足');
            return ['reply' => null, 'error' => 'RAG 拒絕作答（' . $why . '）'];
        }

        $answer = $payload['answer'] ?? null;
        if (!is_string($answer) || trim($answer) === '') {
            return ['reply' => null, 'error' => 'RAG 沒有給出草稿'];
        }

        return ['reply' => trim($answer), 'error' => null];
    }
}
