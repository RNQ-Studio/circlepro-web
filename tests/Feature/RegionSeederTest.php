<?php

namespace Tests\Feature;

use App\Models\Region;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionSeederTest extends TestCase
{
    use RefreshDatabase;

    private static bool $dataFilesPresent;

    protected function setUp(): void
    {
        parent::setUp();

        self::$dataFilesPresent = $this->dataFilesExist();

        if (! self::$dataFilesPresent) {
            $this->markTestSkipped('Region source data not found. Run: php artisan regions:download');
        }
    }

    public function test_country_seeder_inserts_countries(): void
    {
        $this->seed(CountrySeeder::class);

        $count = Region::countries()->count();
        $this->assertGreaterThanOrEqual(200, $count, "Expected at least 200 countries, got {$count}");

        $indonesia = Region::countries()->where('code', 'ID')->first();
        $this->assertNotNull($indonesia, 'Indonesia (ID) must be present');
        $this->assertNotEmpty($indonesia->phone_code);

        $us = Region::countries()->where('code', 'US')->first();
        $this->assertNotNull($us);
        $meta = $us->meta;
        $this->assertArrayHasKey('emoji', $meta);
    }

    public function test_state_seeder_inserts_states_and_id_provinces(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(StateSeeder::class);

        $totalStates = Region::states()->count();
        $this->assertGreaterThanOrEqual(100, $totalStates);

        $indonesiaId = Region::countries()->where('code', 'ID')->value('id');
        $idProvinces = Region::states()->where('parent_id', $indonesiaId)->count();
        $this->assertGreaterThanOrEqual(30, $idProvinces, "Expected at least 30 Indonesian provinces, got {$idProvinces}");

        // BPS code stored in code column
        $aceh = Region::states()->where('code', '11')->first();
        $this->assertNotNull($aceh, 'Province with BPS code 11 (Aceh) must exist');
    }

    public function test_full_hierarchy_traversal(): void
    {
        $this->seed(RegionSeeder::class);

        // Country to state to city to district to village for Indonesia
        $indonesia = Region::countries()->where('code', 'ID')->first();
        $this->assertNotNull($indonesia);

        $province = $indonesia->children()->where('type', 'state')->first();
        $this->assertNotNull($province);

        $city = $province->children()->where('type', 'city')->first();
        $this->assertNotNull($city);

        $district = $city->children()->where('type', 'district')->first();
        $this->assertNotNull($district);

        $village = $district->children()->where('type', 'village')->first();
        $this->assertNotNull($village);

        // Non-Indonesia: country to state to city
        $us = Region::countries()->where('code', 'US')->first();
        $usState = $us?->children()->where('type', 'state')->first();
        $this->assertNotNull($usState, 'US should have at least one state');

        $usCity = $usState->children()->where('type', 'city')->first();
        $this->assertNotNull($usCity, 'US state should have at least one city');
    }

    public function test_full_seeder_record_counts(): void
    {
        $this->seed(RegionSeeder::class);

        $this->assertGreaterThanOrEqual(200, Region::countries()->count(), 'countries');
        $this->assertGreaterThanOrEqual(1000, Region::states()->count(), 'states');
        $this->assertGreaterThanOrEqual(1000, Region::cities()->count(), 'cities');
        $this->assertGreaterThanOrEqual(1000, Region::districts()->count(), 'districts');
        $this->assertGreaterThanOrEqual(1000, Region::villages()->count(), 'villages');
    }

    private function dataFilesExist(): bool
    {
        $required = [
            storage_path('app/regions/dr5hn/countries.json'),
            storage_path('app/regions/dr5hn/states.json'),
            storage_path('app/regions/dr5hn/cities.json'),
            storage_path('app/regions/emsifa/provinces.json'),
            storage_path('app/regions/emsifa/regencies.json'),
            storage_path('app/regions/emsifa/districts.json'),
            storage_path('app/regions/emsifa/villages.json'),
        ];

        foreach ($required as $file) {
            if (! file_exists($file)) {
                return false;
            }
        }

        return true;
    }
}
