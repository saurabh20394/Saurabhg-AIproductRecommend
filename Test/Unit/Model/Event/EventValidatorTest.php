<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Event;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Event\EventValidator;
use Saurabhg\AIproductRecommend\Model\Recommendation\TrackingToken;

class EventValidatorTest extends TestCase
{
    private EventValidator $validator;

    /**
     * @var TrackingToken|Stub
     */
    private $token;

    protected function setUp(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isSetFlag')->willReturn(true);
        $this->token = $this->createStub(TrackingToken::class);
        $this->validator = new EventValidator($config, $this->token);
    }

    public function testNormalizesProductViewWithServerIdentity(): void
    {
        $row = $this->validator->normalize(
            ['event_id' => 'w0123456789abcdef', 'event_type' => 'product_view', 'product_id' => '42', 'timestamp' => (time() * 1000)],
            1,
            null,
            'vabcdefabcdefabcdef',
            'web'
        );
        $this->assertSame('w0123456789abcdef', $row['event_uuid']);
        $this->assertSame(42, $row['subject_id']);
        $this->assertSame('vabcdefabcdefabcdef', $row['visitor_id']);
        $this->assertNull($row['customer_id']);
        $this->assertSame(gmdate('Y-m-d H:i', time()), substr($row['created_at'], 0, 16), 'millisecond timestamps are accepted');
    }

    public function testClientCannotSubmitPurchases(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->normalize(['event_type' => 'purchase', 'product_id' => 1, 'revenue' => 999], 1, 5, null, 'api');
    }

    public function testTrustedPurchaseKeepsOrderData(): void
    {
        $row = $this->validator->normalize(
            ['event_type' => 'purchase', 'product_id' => 1, 'order_id' => 9, 'order_item_id' => 11, 'revenue' => '19.999', 'quantity' => 2],
            1,
            5,
            null,
            'server',
            true
        );
        $this->assertSame(9, $row['order_id']);
        $this->assertSame(11, $row['order_item_id']);
        $this->assertSame(19.999, $row['revenue']);
        $this->assertSame(2.0, $row['qty']);
        $this->assertStringStartsWith('e', $row['event_uuid']);
    }

    public function testRequiresIdentityAndFields(): void
    {
        foreach ([
            [['event_type' => 'product_view', 'product_id' => 1], null, null],
            [['event_type' => 'product_view'], 3, null],
            [['event_type' => 'search', 'query' => '  '], 3, null],
            [['event_type' => 'category_view'], 3, null],
            [['event_type' => 'product_view', 'product_id' => 1, 'event_id' => 'bad id!'], 3, null],
        ] as [$raw, $customer, $visitor]) {
            try {
                $this->validator->normalize($raw, 1, $customer, $visitor, 'api');
                $this->fail('Expected rejection of ' . json_encode($raw));
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testRecommendationEventsUseVerifiedTokenOfSameStore(): void
    {
        $this->token->method('decode')->willReturnMap([
            ['good', ['unit_code' => 'pdp_similar', 'request_id' => 'r1', 'store_id' => 1, 'experiment_id' => 4, 'variant_id' => 8]],
            ['other-store', ['unit_code' => 'pdp_similar', 'request_id' => 'r1', 'store_id' => 2, 'experiment_id' => 4, 'variant_id' => 8]],
        ]);
        $row = $this->validator->normalize(
            ['event_type' => 'recommendation_click', 'sku' => 'A', 'position' => 300, 'tracking_token' => 'good'],
            1,
            7,
            null,
            'api'
        );
        $this->assertSame('pdp_similar', $row['unit_code']);
        $this->assertSame(8, $row['variant_id']);
        $this->assertSame(100, $row['position'], 'position is clamped');

        $this->expectException(\InvalidArgumentException::class);
        $this->validator->normalize(
            ['event_type' => 'recommendation_impression', 'sku' => 'A', 'tracking_token' => 'other-store'],
            1,
            7,
            null,
            'api'
        );
    }

    public function testSearchQueryIsSanitized(): void
    {
        $row = $this->validator->normalize(
            ['event_type' => 'search', 'query' => "<b>red</b>\n  shoes"],
            1,
            7,
            null,
            'web'
        );
        $this->assertSame('red shoes', $row['search_query']);
    }
}
