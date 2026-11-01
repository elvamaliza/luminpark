<?php

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::updateOrCreate(
            ['name' => 'Perumahan Lumin Park Semarang'],
            [
                'description' => 'Pengembangan perumahan menengah ke atas dengan tiga tipe rumah dan beberapa cluster.',
                'address'     => 'Jl. Raya Semarang, Jawa Tengah',
                'status'      => 'active',
            ]
        );

        $clusters = [
            ['name' => 'Cluster Asena', 'description' => 'Cluster depan dengan akses langsung ke fasilitas utama.'],
            ['name' => 'Cluster Bruna', 'description' => 'Cluster tengah dengan konsep taman hijau.'],
            ['name' => 'Cluster Cendana', 'description' => 'Cluster tahap dua, dekat area komersial.'],
        ];

        $types = [
            ['type' => 'Modern Tropical 60', 'land' => 72, 'building' => 60, 'price' => 750000000],
            ['type' => 'Modern Tropical 90', 'land' => 96, 'building' => 90, 'price' => 1050000000],
            ['type' => 'Skandinavia 120', 'land' => 120, 'building' => 120, 'price' => 1450000000],
        ];

        $sequence = 1;

        foreach ($clusters as $clusterData) {
            $cluster = Cluster::updateOrCreate(
                ['project_id' => $project->id, 'name' => $clusterData['name']],
                ['description' => $clusterData['description']]
            );

            foreach ($types as $t) {
                $code = 'LT-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

                Unit::updateOrCreate(
                    ['unit_code' => $code],
                    [
                        'cluster_id'    => $cluster->id,
                        'unit_type'     => $t['type'],
                        'land_size'     => $t['land'],
                        'building_size' => $t['building'],
                        'price'         => $t['price'],
                        'status'        => Unit::STATUS_AVAILABLE,
                        'description'   => $t['type'] . ' di ' . $clusterData['name'] . ' — ' . $t['land'] . 'm² / ' . $t['building'] . 'm².',
                    ]
                );

                $sequence++;
            }
        }
    }
}
