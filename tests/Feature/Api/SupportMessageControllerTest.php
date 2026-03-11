<?php

use App\Mail\AdminReplyToSupportMessageMail;
use App\Mail\UserSupportMessageToAdminMail;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'user']);
    $this->userToken = JWTAuth::fromUser($this->user);

    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->adminToken = JWTAuth::fromUser($this->admin);
});

test('authenticated user can send support message to admins', function () {
    Mail::fake();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->userToken)
        ->postJson('/api/support-messages/send', [
            'subject' => 'Need help with my order',
            'message' => 'I cannot update my delivery address.',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.user_id', $this->user->id)
        ->assertJsonPath('data.subject', 'Need help with my order')
        ->assertJsonPath('data.status', 'new');

    $this->assertDatabaseHas('support_messages', [
        'user_id' => $this->user->id,
        'subject' => 'Need help with my order',
        'status' => 'new',
    ]);

    Mail::assertSent(UserSupportMessageToAdminMail::class, function ($mail) {
        return $mail->hasTo($this->admin->email);
    });
});

test('admin can view support messages list', function () {
    SupportMessage::create([
        'user_id' => $this->user->id,
        'subject' => 'Payment issue',
        'message' => 'My card was charged twice.',
        'status' => 'new',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/support-messages/admin/get-all');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.user.id', $this->user->id)
        ->assertJsonPath('data.data.0.subject', 'Payment issue');
});

test('admin can reply to a support message and email is sent to user', function () {
    Mail::fake();

    $supportMessage = SupportMessage::create([
        'user_id' => $this->user->id,
        'subject' => 'App crash report',
        'message' => 'The app closes when I open orders.',
        'status' => 'new',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson("/api/support-messages/admin/reply/{$supportMessage->id}", [
            'subject' => 'Re: App crash report',
            'message' => 'Please update to the latest app version and try again.',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $supportMessage->id)
        ->assertJsonPath('data.status', 'replied')
        ->assertJsonPath('data.reply_subject', 'Re: App crash report')
        ->assertJsonPath('data.replied_by', $this->admin->id);

    $this->assertDatabaseHas('support_messages', [
        'id' => $supportMessage->id,
        'status' => 'replied',
        'reply_subject' => 'Re: App crash report',
        'replied_by' => $this->admin->id,
    ]);

    Mail::assertSent(AdminReplyToSupportMessageMail::class, function ($mail) {
        return $mail->hasTo($this->user->email);
    });
});
