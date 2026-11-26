<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * OASIS chapters: a chapter for each of the 36 states and the FCT, and a
 * regional chapter for each of Nigeria's six geopolitical zones, made up of
 * its states. The list is fixed by the country's map, so it ships with the
 * migration and every database gets the same rows.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const REGIONS = [
        'North Central' => ['Benue', 'Federal Capital Territory', 'Kogi', 'Kwara', 'Nasarawa', 'Niger', 'Plateau'],
        'North East' => ['Adamawa', 'Bauchi', 'Borno', 'Gombe', 'Taraba', 'Yobe'],
        'North West' => ['Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Sokoto', 'Zamfara'],
        'South East' => ['Abia', 'Anambra', 'Ebonyi', 'Enugu', 'Imo'],
        'South South' => ['Akwa Ibom', 'Bayelsa', 'Cross River', 'Delta', 'Edo', 'Rivers'],
        'South West' => ['Ekiti', 'Lagos', 'Ogun', 'Ondo', 'Osun', 'Oyo'],
    ];

    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->timestamps();
        });

        $now = now();

        foreach (self::REGIONS as $region => $states) {
            $regionId = DB::table('regions')->insertGetId([
                'name' => $region,
                'slug' => Str::slug($region),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('states')->insert(array_map(fn (string $state) => [
                'region_id' => $regionId,
                'name' => $state,
                'slug' => Str::slug($state),
                'created_at' => $now,
                'updated_at' => $now,
            ], $states));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
        Schema::dropIfExists('regions');
    }
};
