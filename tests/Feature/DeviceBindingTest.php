<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class DeviceBindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal schema needed for these tests without running full migrations
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('status')->default('active');
            $table->string('remember_token')->nullable();
            $table->foreignId('bound_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_fingerprint')->unique();
            $table->string('device_name')->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('os', 100)->nullable();
            $table->string('browser', 100)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by_ip', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type', 'role_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action')->nullable();
            $table->string('event')->nullable();
            $table->string('module')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    public function test_user_first_login_binds_device()
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'polling-officer',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
        $user->refresh();

        $device = Device::factory()->create(['user_id' => $user->id]);

        // Simulate cookie fingerprint present
        $response = $this->withHeaders([
                'X-DEVICE-ID' => $device->device_fingerprint,
            ])->withCookie(\App\Services\DeviceBindingService::DEVICE_COOKIE_NAME, $device->device_fingerprint)
            ->post('/auth/login', ['email' => $user->email, 'password' => 'secret']);

        $response->assertRedirect('/auth/two-factor');
        $response->assertSessionHas('2fa_user_id', $user->id);
        $response->assertSessionHas('2fa_expires_at');

        // Retrieve the generated 2FA code from cache and verify
        $code = \Illuminate\Support\Facades\Cache::get("2fa_code_{$user->id}");
        $this->assertNotNull($code, '2FA code should be present in cache');
        $this->assertDatabaseHas('devices', ['device_fingerprint' => $device->device_fingerprint]);

        $verifyResponse = $this->withHeaders([
                'X-DEVICE-ID' => $device->device_fingerprint,
            ])
            ->withSession([
                '2fa_user_id' => $user->id,
                '2fa_expires_at' => now()->addMinutes(10)->timestamp,
            ])
            ->post('/auth/two-factor', ['code' => $code]);

        $verifyResponse->assertSessionHasNoErrors();
        $verifyResponse->assertRedirect('/officer/dashboard');

        $user->refresh();
        $this->assertNotNull($user->bound_device_id);
        $this->assertEquals($device->id, $user->bound_device_id);
    }

    public function test_login_from_unbound_different_device_is_blocked()
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'polling-officer',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
        $user->refresh();
        $device1 = Device::factory()->create(['user_id' => $user->id]);
        $device2 = Device::factory()->create(['user_id' => $user->id]);

        // First bind to device1
        $this->withCookie(\App\Services\DeviceBindingService::DEVICE_COOKIE_NAME, $device1->device_fingerprint)
            ->post('/auth/login', ['email' => $user->email, 'password' => 'secret']);
        $code = \Illuminate\Support\Facades\Cache::get("2fa_code_{$user->id}");
        $this->assertNotNull($code, '2FA code should be present in cache');
        $this->assertDatabaseHas('devices', ['device_fingerprint' => $device1->device_fingerprint]);
        $this->withCookie(\App\Services\DeviceBindingService::DEVICE_COOKIE_NAME, $device1->device_fingerprint)
            ->post('/auth/two-factor', ['code' => $code], ['HTTP_X_DEVICE_ID' => $device1->device_fingerprint]);
        $user->refresh();
        $this->assertEquals($device1->id, $user->bound_device_id);

        $this->post('/logout', ['_token' => $this->app['session']->token()]);

        // Try logging in from device2
        $response = $this->withCookie(\App\Services\DeviceBindingService::DEVICE_COOKIE_NAME, $device2->device_fingerprint)
            ->post('/auth/login', ['email' => $user->email, 'password' => 'secret']);
        $response->assertRedirect('/auth/two-factor');

        $device2Code = \Illuminate\Support\Facades\Cache::get("2fa_code_{$user->id}");
        $response2 = $this->withHeaders([
                'X-DEVICE-ID' => $device2->device_fingerprint,
            ])->withSession([
                '2fa_user_id' => $user->id,
                '2fa_expires_at' => now()->addMinutes(10)->timestamp,
            ])
            ->post('/auth/two-factor', ['code' => $device2Code]);

        $response2->assertStatus(403);
        $response2->assertSessionHasErrors();
    }
}
