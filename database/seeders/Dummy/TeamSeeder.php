<?php

namespace Database\Seeders\Dummy;

use App\Models\Team;
use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create('nl_NL');

        $teams = [
            'support',
            'development',
            'service management',
        ];

        foreach ($teams as $teamName) {
            $team = Team::firstOrCreate(['name' => $teamName]);

            if ($teamName === 'development') {
                User::whereNull('team_id')->first()?->update(['team_id' => $team->id]);
            }

            for ($i = 0; $i < 6; $i++) {
                $givenName = $faker->firstName();
                $familyName = $faker->lastName();

                $email = Str::slug($givenName.' '.$familyName).'@timatic.app';

                $user = new User;
                $user->email = $email;
                $user->external_id = $faker->unique()->uuid();
                $user->given_name = $givenName;
                $user->family_name = $familyName;
                $user->team_id = $team->id;
                $user->save();
            }
        }
    }
}
