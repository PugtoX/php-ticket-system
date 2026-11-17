<?php
declare(strict_types=1);

namespace Tests;

use App\RagSuggester;
use PHPUnit\Framework\TestCase;

final class RagSuggesterTest extends TestCase
{
    public function testQuestionCarriesTheTicketAndTheConstraints(): void
    {
        $q = RagSuggester::buildQuestion('Order not delivered', 'Paid on the 3rd, still waiting.');

        self::assertStringContainsString('Order not delivered', $q);
        self::assertStringContainsString('Paid on the 3rd, still waiting.', $q);
        self::assertStringContainsString('繁體中文', $q);
        self::assertStringContainsString('不要承諾', $q);
    }

    public function testAnswerBecomesTheDraft(): void
    {
        $r = RagSuggester::interpret(['answer' => "  已收到你的查詢。\n", 'refused' => false]);

        self::assertSame("已收到你的查詢。", $r['reply']);
        self::assertNull($r['error']);
    }

    public function testRefusalIsAnErrorNotAnEmptyDraft(): void
    {
        $r = RagSuggester::interpret(['refused' => true, 'reason' => '檢索相似度不足，未呼叫模型', 'answer' => null]);

        self::assertNull($r['reply']);
        self::assertStringContainsString('拒絕作答', (string) $r['error']);
        self::assertStringContainsString('檢索相似度不足', (string) $r['error']);
    }

    public function testServiceErrorIsSurfaced(): void
    {
        $r = RagSuggester::interpret(['error' => 'RuntimeError: model not loaded', 'answer' => null]);

        self::assertNull($r['reply']);
        self::assertStringContainsString('model not loaded', (string) $r['error']);
    }

    public function testMissingOrBlankAnswerIsNotTreatedAsSuccess(): void
    {
        foreach ([['answer' => null], ['answer' => '   '], []] as $payload) {
            $r = RagSuggester::interpret($payload);
            self::assertNull($r['reply']);
            self::assertSame('RAG 沒有給出草稿', $r['error']);
        }
    }

    public function testNonJsonPayloadIsRejected(): void
    {
        self::assertSame('RAG 回應不是 JSON 物件', RagSuggester::interpret('oops')['error']);
    }
}
