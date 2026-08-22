<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\GalleryImage;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GalleryEventScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_index_returns_only_current_open_event_items(): void
    {
        [$stream, $past, $current] = $this->streamWithTwoEvents();

        GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $past->id,
            'path' => 'gallery/past.jpg',
            'media_type' => 'image',
        ]);
        $currentImage = GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $current->id,
            'path' => 'gallery/current.jpg',
            'media_type' => 'image',
        ]);
        GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => null,
            'path' => 'gallery/orphan.jpg',
            'media_type' => 'image',
        ]);

        $this->getJson(route('gallery.index', $stream))
            ->assertOk()
            ->assertJsonPath('event_id', $current->id)
            ->assertJsonCount(1, 'images')
            ->assertJsonPath('images.0.id', $currentImage->id);
    }

    public function test_gallery_index_empty_when_no_open_event(): void
    {
        [$stream, $past] = $this->streamWithEndedEventOnly();

        GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $past->id,
            'path' => 'gallery/past.jpg',
            'media_type' => 'image',
        ]);

        $this->getJson(route('gallery.index', $stream))
            ->assertOk()
            ->assertJsonPath('event_id', null)
            ->assertJsonCount(0, 'images');
    }

    public function test_gallery_index_can_request_specific_event(): void
    {
        [$stream, $past, $current] = $this->streamWithTwoEvents();

        $pastImage = GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $past->id,
            'path' => 'gallery/past.jpg',
            'media_type' => 'image',
        ]);
        GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $current->id,
            'path' => 'gallery/current.jpg',
            'media_type' => 'image',
        ]);

        $this->getJson(route('gallery.index', ['stream' => $stream, 'event_id' => $past->id]))
            ->assertOk()
            ->assertJsonPath('event_id', $past->id)
            ->assertJsonCount(1, 'images')
            ->assertJsonPath('images.0.id', $pastImage->id);
    }

    public function test_event_page_only_shows_that_events_gallery(): void
    {
        [$stream, $past, $current] = $this->streamWithTwoEvents();

        GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $past->id,
            'path' => 'gallery/past.jpg',
            'media_type' => 'image',
            'caption' => 'Past service photo',
        ]);
        GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $current->id,
            'path' => 'gallery/current.jpg',
            'media_type' => 'image',
            'caption' => 'Current service photo',
        ]);

        $this->get(route('events.show', $current))
            ->assertOk()
            ->assertSee('Current service photo', false)
            ->assertDontSee('Past service photo', false);
    }

    public function test_studio_page_hides_prior_event_gallery_when_standby(): void
    {
        [$user, $stream] = $this->creatorStream();
        $past = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Last Sunday',
            'status' => EventStatus::Ended,
            'access' => EventAccess::Public,
            'ended_at' => now()->subDay(),
        ]);
        GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $past->id,
            'path' => 'gallery/past.jpg',
            'media_type' => 'image',
            'caption' => 'Should not appear in standby',
        ]);

        $url = URL::temporarySignedRoute('studio.stream', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertDontSee('Should not appear in standby', false)
            ->assertDontSee('gallery/past.jpg', false);
    }

    public function test_upload_attaches_to_open_event_and_new_event_starts_empty(): void
    {
        Storage::fake('public');
        [$user, $stream] = $this->creatorStream();

        $first = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'First',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);

        $upload = URL::temporarySignedRoute('gallery.store', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->post($upload, [
                'image' => UploadedFile::fake()->image('a.jpg'),
                'event_id' => $first->id,
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertDatabaseHas('gallery_images', [
            'stream_id' => $stream->id,
            'event_id' => $first->id,
        ]);

        $first->update(['status' => EventStatus::Ended, 'ended_at' => now()]);
        $second = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Second',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);

        $this->getJson(route('gallery.index', ['stream' => $stream, 'event_id' => $second->id]))
            ->assertOk()
            ->assertJsonCount(0, 'images')
            ->assertJsonPath('event_id', $second->id);

        $this->getJson(route('gallery.index', $stream))
            ->assertOk()
            ->assertJsonCount(0, 'images')
            ->assertJsonPath('event_id', $second->id);
    }

    public function test_upload_rejected_without_open_event(): void
    {
        Storage::fake('public');
        [$user, $stream] = $this->creatorStream();

        $upload = URL::temporarySignedRoute('gallery.store', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->post($upload, [
                'image' => UploadedFile::fake()->image('a.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_id']);
    }

    public function test_studio_page_shows_hover_delete_on_open_event_thumbs(): void
    {
        [$user, $stream] = $this->creatorStream();
        $open = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Sunday',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);
        $image = GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $open->id,
            'path' => 'gallery/live.jpg',
            'media_type' => 'image',
            'caption' => 'Live gallery photo',
        ]);

        $url = URL::temporarySignedRoute('studio.stream', now()->addHour(), ['stream' => $stream]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertSee('mixer-gallery-delete', false)
            ->assertSee('Remove photo from gallery', false)
            ->assertSee('data-id="'.$image->id.'"', false)
            ->assertSee('data-gallery-destroy-url', false);
    }

    public function test_listen_page_gallery_is_view_only(): void
    {
        [$user, $stream] = $this->creatorStream();
        $open = Event::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'title' => 'Sunday',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);
        GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'event_id' => $open->id,
            'path' => 'gallery/live.jpg',
            'media_type' => 'image',
            'caption' => 'Listener photo',
        ]);

        $this->actingAs($user)
            ->get(route('events.show', $open))
            ->assertOk()
            ->assertSee('Listener photo', false)
            ->assertDontSee('mixer-gallery-delete', false);
    }

    public function test_signed_studio_route_deletes_gallery_item(): void
    {
        Storage::fake('public');
        [, $stream] = $this->creatorStream();
        $path = UploadedFile::fake()->image('live.jpg')->store('gallery/'.$stream->uuid, 'public');
        $image = GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'path' => $path,
            'media_type' => 'image',
        ]);

        $destroy = URL::temporarySignedRoute(
            'studio.gallery.destroy',
            now()->addHour(),
            ['stream' => $stream],
        );

        $this->deleteJson($destroy, ['image_id' => $image->id])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('gallery_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_guest_cannot_delete_gallery_without_signature(): void
    {
        [, $stream] = $this->creatorStream();
        $image = GalleryImage::query()->create([
            'organization_id' => $stream->organization_id,
            'stream_id' => $stream->id,
            'path' => 'gallery/live.jpg',
            'media_type' => 'image',
        ]);

        $this->deleteJson(route('studio.gallery.destroy', $stream), ['image_id' => $image->id])
            ->assertForbidden();

        $this->assertDatabaseHas('gallery_images', ['id' => $image->id]);
    }

    /**
     * @return array{0: Stream, 1: Event, 2: Event}
     */
    private function streamWithTwoEvents(): array
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
            'is_public' => true,
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Live,
            'is_public' => true,
        ]);
        $past = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Past',
            'status' => EventStatus::Ended,
            'access' => EventAccess::Public,
            'ended_at' => now()->subDay(),
        ]);
        $current = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Current',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);

        return [$stream, $past, $current];
    }

    /**
     * @return array{0: Stream, 1: Event}
     */
    private function streamWithEndedEventOnly(): array
    {
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
            'is_public' => true,
        ]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
            'is_public' => true,
        ]);
        $past = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Past',
            'status' => EventStatus::Ended,
            'access' => EventAccess::Public,
            'ended_at' => now()->subDay(),
        ]);

        return [$stream, $past];
    }

    /**
     * @return array{0: User, 1: Stream}
     */
    private function creatorStream(): array
    {
        $user = User::factory()->create();
        $org = Organization::query()->create([
            'name' => 'Church',
            'slug' => 'church-'.uniqid(),
            'is_public' => true,
        ]);
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
            'is_public' => true,
        ]);

        return [$user, $stream];
    }
}
