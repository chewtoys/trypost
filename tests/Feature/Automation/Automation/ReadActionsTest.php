<?php

declare(strict_types=1);

use App\Actions\Automation\Automation\DeleteAutomation;
use App\Actions\Automation\Automation\GetAutomationDetails;
use App\Actions\Automation\Automation\GetAutomationEditorData;
use App\Actions\Automation\Automation\ListAutomations;
use App\Enums\SocialAccount\Platform;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationTriggerItem;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\PinterestPublisher;
use App\Services\Social\TikTokCreatorInfo;

it('lists only the workspace automations, newest first', function () {
    $workspace = Workspace::factory()->create();
    $other = Workspace::factory()->create();

    $older = Automation::factory()->for($workspace)->create(['created_at' => now()->subDay()]);
    $newer = Automation::factory()->for($workspace)->create(['created_at' => now()]);
    Automation::factory()->for($other)->create();

    $result = app(ListAutomations::class)($workspace);

    expect($result->total())->toBe(2);
    expect($result->items()[0]->id)->toBe($newer->id);
    expect($result->items()[1]->id)->toBe($older->id);
});

it('deletes the automation', function () {
    $automation = Automation::factory()->create();

    app(DeleteAutomation::class)($automation);

    expect(Automation::find($automation->id))->toBeNull();
});

it('returns non-dry runs and trigger items, newest first', function () {
    $automation = Automation::factory()->create();
    $real = AutomationRun::factory()->for($automation)->create();
    AutomationRun::factory()->for($automation)->create(['is_dry_run' => true]);
    $item = AutomationTriggerItem::factory()->for($automation)->create();

    $result = app(GetAutomationDetails::class)($automation);

    expect($result['runs'])->toHaveCount(1);
    expect($result['runs']->first()->id)->toBe($real->id);
    expect($result['triggerItems'])->toHaveCount(1);
    expect($result['triggerItems']->first()->id)->toBe($item->id);
});

it('returns only active social accounts for the automation workspace', function () {
    $this->mock(PinterestPublisher::class);
    $this->mock(TikTokCreatorInfo::class);

    $workspace = Workspace::factory()->create();
    $automation = Automation::factory()->for($workspace)->create();
    $active = SocialAccount::factory()->for($workspace)->create(['platform' => 'instagram', 'is_active' => true]);
    SocialAccount::factory()->for($workspace)->create(['platform' => 'instagram', 'is_active' => false]);

    $result = app(GetAutomationEditorData::class)($automation);

    expect($result['socialAccounts'])->toHaveCount(1);
    expect($result['socialAccounts']->first()->id)->toBe($active->id);
    expect($result['pinterestBoards'])->toBeEmpty();
    expect($result['tiktokCreatorInfos'])->toBeEmpty();
});

it('maps pinterest boards for pinterest accounts', function () {
    $this->mock(PinterestPublisher::class, fn ($mock) => $mock->shouldReceive('getBoards')->andReturn([['id' => 'b1']]));
    $this->mock(TikTokCreatorInfo::class);

    $workspace = Workspace::factory()->create();
    $automation = Automation::factory()->for($workspace)->create();
    $pinterest = SocialAccount::factory()->for($workspace)->create(['platform' => Platform::Pinterest->value, 'is_active' => true]);

    $result = app(GetAutomationEditorData::class)($automation);

    expect($result['pinterestBoards']->get($pinterest->id))->toBe([['id' => 'b1']]);
});
