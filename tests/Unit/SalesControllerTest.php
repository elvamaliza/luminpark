<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\SalesController;
use App\Models\SalesProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class SalesControllerTest extends TestCase
{
    public function test_sales_ranking_sorts_by_booking_count_then_name(): void
    {
        $method = new ReflectionMethod(SalesController::class, 'sortRankingRows');
        $method->setAccessible(true);

        $rows = collect([
            $this->salesRow('Budi', 2),
            $this->salesRow('Andi', 2),
            $this->salesRow('Citra', 1),
            $this->salesRow('Dina', 0),
        ]);

        $ranked = $method->invoke(new SalesController(), $rows);

        $this->assertSame(['Andi', 'Budi', 'Citra', 'Dina'], $ranked->map(fn (SalesProfile $row) => $row->user->name)->all());
    }

    private function salesRow(string $name, int $bookings): SalesProfile
    {
        $profile = new SalesProfile(['id' => $bookings + 1]);
        $profile->setAttribute('bookings_count', $bookings);
        $profile->setRelation('user', new User(['name' => $name]));
        return $profile;
    }
}
