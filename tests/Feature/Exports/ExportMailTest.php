<?php

use App\Mail\ExportEmail;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('sends an email with export file', function () {
    Budget::query()->delete();

    $user = User::factory()->create();
    $this->actingAs($user);
    Storage::fake('s3');

    Mail::fake();

    $response = $this->get(route('budgets.export-mail', [
        'exportType' => 'budgets-excel',
        'year' => 2024,
        'month' => 10,
    ]));
    $response->assertStatus(202);

    Mail::assertQueued(ExportEmail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) && strpos($mail->render(), route('download.export', ['fileName' => $mail->fileName])) !== false;
    });
});
