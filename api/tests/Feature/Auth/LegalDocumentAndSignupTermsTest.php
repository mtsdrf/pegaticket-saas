<?php

namespace Tests\Feature\Auth;

use App\Models\Legal\LegalDocument;
use App\Models\User\User;
use Database\Seeders\ActionsSeeder;
use Database\Seeders\FunctionalitiesSeeder;
use Database\Seeders\GroupsSeeder;
use Database\Seeders\InitialPlansSeeder;
use Database\Seeders\LegalDocumentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalDocumentAndSignupTermsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_endpoint_returns_active_terms(): void
    {
        LegalDocument::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'terms',
            'version' => 'v1',
            'content' => 'Conteúdo dos termos',
            'published_at' => now(),
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/legal-documents/terms')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'terms')
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonPath('data.content', 'Conteúdo dos termos');
    }

    #[Test]
    public function unknown_type_is_not_routable(): void
    {
        $this->getJson('/api/v1/legal-documents/cookies')
            ->assertStatus(404);
    }

    #[Test]
    public function returns_404_when_no_active_document(): void
    {
        $this->getJson('/api/v1/legal-documents/privacy')
            ->assertStatus(404);
    }

    #[Test]
    public function signup_fails_without_required_legal_acceptances(): void
    {
        $this->seed([ActionsSeeder::class, FunctionalitiesSeeder::class, InitialPlansSeeder::class, GroupsSeeder::class]);

        $this->postJson('/api/v1/auth/signup', [
            'owner_name' => 'No Terms',
            'owner_email' => 'noterms@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tenant_name' => 'Empresa',
            'tenant_slug' => 'empresa-' . Str::random(6),
        ])->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['terms_accepted', 'privacy_accepted']);
    }

    #[Test]
    public function signup_records_acceptance_of_active_terms_and_privacy(): void
    {
        $this->seed([
            ActionsSeeder::class,
            FunctionalitiesSeeder::class,
            InitialPlansSeeder::class,
            GroupsSeeder::class,
            LegalDocumentsSeeder::class,
        ]);

        $this->postJson('/api/v1/auth/signup', [
            'owner_name' => 'With Terms',
            'owner_email' => 'withterms@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tenant_name' => 'Empresa OK',
            'tenant_slug' => 'empresa-ok-' . Str::random(6),
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ])->assertStatus(201);

        $user = User::where('email', 'withterms@test.com')->first();
        $this->assertNotNull($user);

        $termsId = LegalDocument::where('type', 'terms')->where('is_active', true)->value('id');
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'legal_document_id' => $termsId,
        ]);

        $privacyId = LegalDocument::where('type', 'privacy')->where('is_active', true)->value('id');
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'legal_document_id' => $privacyId,
        ]);
    }
}
