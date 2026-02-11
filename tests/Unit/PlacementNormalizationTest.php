<?php

namespace Tests\Unit;

use App\Models\Placement;
use PHPUnit\Framework\TestCase;

class PlacementNormalizationTest extends TestCase
{
    public function test_normalize_int_list_supports_csv_string(): void
    {
        $normalized = Placement::normalizeIntList('1,2,abc,0,3');

        $this->assertSame([1, 2, 3], $normalized);
    }

    public function test_get_offer_ids_supports_csv_legacy_config(): void
    {
        $placement = new Placement();
        $placement->config = ['offer_ids' => '5,6,7'];

        $this->assertSame([5, 6, 7], $placement->getOfferIds());
    }

    public function test_get_block_ids_supports_csv_legacy_config(): void
    {
        $placement = new Placement();
        $placement->config = ['block_ids' => '11,12'];

        $this->assertSame([11, 12], $placement->getBlockIds());
    }
}

