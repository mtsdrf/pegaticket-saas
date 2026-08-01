<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class SeederSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeders_run_cleanly_and_wire_up_tickets_functionality(): void
    {
        $this->seed();

        $this->assertDatabaseHas('functionalities', ['slug' => 'tickets']);
        $this->assertDatabaseHas('actions', ['key' => 'checkin']);
        $this->assertDatabaseHas('actions', ['key' => 'resend']);

        $planId = DB::table('plans')->where('slug', 'pegaticket')->value('id');
        $funcId = DB::table('functionalities')->where('slug', 'tickets')->value('id');
        $this->assertDatabaseHas('plan_functionalities', ['plan_id' => $planId, 'functionality_id' => $funcId]);

        $groupId = DB::table('groups')->where('slug', 'administrators')->value('id');
        $checkinActionId = DB::table('actions')->where('key', 'checkin')->value('id');
        $this->assertDatabaseHas('group_permissions', [
            'group_id' => $groupId,
            'functionality_id' => $funcId,
            'action_id' => $checkinActionId,
        ]);
    }
}
