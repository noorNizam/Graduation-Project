<?php

namespace Tests\Feature;

use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WorkGalleryItem;
use App\Infrastructure\Models\WorkGalleryItemFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkGalleryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create(['role' => 'user']);
        $this->otherUser = User::factory()->create(['role' => 'user']);
    }

    public function test_user_can_add_work_item_without_files(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/work-gallery', [
                'title' => 'My first project',
                'description' => 'A web app',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'My first project')
            ->assertJsonPath('data.description', 'A web app')
            ->assertJsonCount(0, 'data.files');

        $this->assertDatabaseHas('work_gallery_items', [
            'title' => 'My first project',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_add_work_item_with_files(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/work-gallery', [
                'title' => 'Gallery item',
                'date_of_achievement' => '2026-07-01',
                'files' => [
                    UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
                    UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.files');

        $this->assertDatabaseHas('work_gallery_item_files', [
            'work_gallery_item_id' => $response->json('data.id'),
        ]);
        $this->assertSame(2, WorkGalleryItemFile::count());
    }

    public function test_user_can_add_work_item_with_single_file_field(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/work-gallery', [
                'title' => 'Single file',
                'files' => UploadedFile::fake()->create('single.jpg', 100, 'image/jpeg'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.files');
    }

    public function test_title_is_required(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/work-gallery', [
                'description' => 'no title',
            ]);

        $response->assertStatus(422);
    }

    public function test_max_files_is_ten(): void
    {
        $files = [];
        for ($i = 0; $i < 11; $i++) {
            $files[] = UploadedFile::fake()->create("photo_{$i}.jpg", 100, 'image/jpeg');
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/work-gallery', [
                'title' => 'Too many files',
                'files' => $files,
            ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_get_my_items(): void
    {
        WorkGalleryItem::create([
            'title' => 'Item A',
            'user_id' => $this->user->id,
        ]);
        WorkGalleryItem::create([
            'title' => 'Item B',
            'user_id' => $this->user->id,
        ]);
        WorkGalleryItem::create([
            'title' => 'Other user item',
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/work-gallery/my');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Item A'));
        $this->assertTrue($titles->contains('Item B'));
        $this->assertFalse($titles->contains('Other user item'));
    }

    public function test_get_user_items_is_public(): void
    {
        WorkGalleryItem::create([
            'title' => 'Public item',
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->getJson("/api/work-gallery/user/{$this->otherUser->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Public item')
            ->assertJsonPath('data.0.user_full_name', $this->otherUser->full_name);
    }

    public function test_get_user_items_returns_404_for_unknown_user(): void
    {
        $response = $this->getJson('/api/work-gallery/user/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_owner_can_edit_item(): void
    {
        $item = WorkGalleryItem::create([
            'title' => 'Old title',
            'description' => 'Old description',
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/work-gallery/{$item->id}", [
                'title' => 'New title',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.description', 'Old description');
    }

    public function test_edit_with_whitespace_title_keeps_existing_title(): void
    {
        $item = WorkGalleryItem::create([
            'title' => 'Valid title',
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/work-gallery/{$item->id}", [
                'title' => '   ',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Valid title');
    }

    public function test_edit_only_modifies_non_null_properties(): void
    {
        $item = WorkGalleryItem::create([
            'title' => 'Keep title',
            'description' => 'Keep description',
            'date_of_achievement' => '2026-01-01',
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/work-gallery/{$item->id}", [
                'title' => null,
                'description' => 'Updated description',
                'date_of_achievement' => null,
            ]);

        $response->assertStatus(200);

        $fresh = $item->fresh();
        $this->assertSame('Keep title', $fresh->title);
        $this->assertSame('Updated description', $fresh->description);
        $this->assertNotNull($fresh->date_of_achievement);
    }

    public function test_edit_replaces_files_when_provided(): void
    {
        $item = WorkGalleryItem::create([
            'title' => 'Item with files',
            'user_id' => $this->user->id,
        ]);
        WorkGalleryItemFile::create([
            'work_gallery_item_id' => $item->id,
            'file_url' => '/storage/work_gallery/old.jpg',
            'file_type' => 'image/jpeg',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/work-gallery/'.$item->id, [
                'files' => [
                    UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg'),
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.files');

        $this->assertDatabaseMissing('work_gallery_item_files', [
            'file_url' => '/storage/work_gallery/old.jpg',
        ]);
        $this->assertSame(1, WorkGalleryItemFile::count());
    }

    public function test_edit_replaces_single_file_field(): void
    {
        $item = WorkGalleryItem::create([
            'title' => 'Item with single file',
            'user_id' => $this->user->id,
        ]);
        WorkGalleryItemFile::create([
            'work_gallery_item_id' => $item->id,
            'file_url' => '/storage/work_gallery/old.jpg',
            'file_type' => 'image/jpeg',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/work-gallery/'.$item->id, [
                'files' => UploadedFile::fake()->create('single-new.jpg', 100, 'image/jpeg'),
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.files');

        $this->assertDatabaseMissing('work_gallery_item_files', [
            'file_url' => '/storage/work_gallery/old.jpg',
        ]);
        $this->assertSame(1, WorkGalleryItemFile::count());
    }

    public function test_non_owner_cannot_edit_item(): void
    {
        $item = WorkGalleryItem::create([
            'title' => 'Other user item',
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/work-gallery/{$item->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertStatus(403);
        $this->assertSame('Other user item', $item->fresh()->title);
    }

    public function test_owner_can_delete_item(): void
    {
        $item = WorkGalleryItem::create([
            'title' => 'Delete me',
            'user_id' => $this->user->id,
        ]);
        WorkGalleryItemFile::create([
            'work_gallery_item_id' => $item->id,
            'file_url' => '/storage/work_gallery/to-delete.jpg',
            'file_type' => 'image/jpeg',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/work-gallery/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('work_gallery_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('work_gallery_item_files', ['work_gallery_item_id' => $item->id]);
    }

    public function test_non_owner_cannot_delete_item(): void
    {
        $item = WorkGalleryItem::create([
            'title' => 'Protected item',
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/work-gallery/{$item->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('work_gallery_items', ['id' => $item->id]);
    }

    public function test_admin_cannot_add_work_item(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/work-gallery', [
                'title' => 'Admin item',
            ]);

        $response->assertStatus(403);
    }
}
