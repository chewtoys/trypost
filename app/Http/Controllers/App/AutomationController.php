<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Automation\Automation\ActivateAutomation;
use App\Actions\Automation\Automation\CreateAutomation;
use App\Actions\Automation\Automation\DeleteAutomation;
use App\Actions\Automation\Automation\GetAutomationDetails;
use App\Actions\Automation\Automation\GetAutomationEditorData;
use App\Actions\Automation\Automation\ListAutomations;
use App\Actions\Automation\Automation\PauseAutomation;
use App\Actions\Automation\Automation\UpdateAutomation;
use App\Actions\Automation\Run\RetryRunFromNode;
use App\Actions\Automation\Run\TestAutomation;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Automations\ActivateAutomationRequest;
use App\Http\Requests\App\Automations\PauseAutomationRequest;
use App\Http\Requests\App\Automations\RetryRunRequest;
use App\Http\Requests\App\Automations\StoreAutomationRequest;
use App\Http\Requests\App\Automations\TestAutomationRequest;
use App\Http\Requests\App\Automations\UpdateAutomationRequest;
use App\Http\Resources\App\PlatformConfigResource;
use App\Http\Resources\App\SocialAccountResource;
use App\Http\Resources\AutomationNodeRunResource;
use App\Http\Resources\AutomationResource;
use App\Http\Resources\AutomationRunResource;
use App\Http\Resources\AutomationTriggerItemResource;
use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function index(ListAutomations $list): Response
    {
        $workspace = request()->user()->currentWorkspace;

        $automations = Inertia::scroll(fn () => AutomationResource::collection(
            $list($workspace)
        ));

        return Inertia::render('automations/Index', [
            'automations' => $automations,
        ]);
    }

    public function store(StoreAutomationRequest $request, CreateAutomation $create): RedirectResponse
    {
        $automation = $create(
            $request->user()->currentWorkspace,
            $request->user(),
        );

        return redirect()->route('app.automations.edit', $automation->id);
    }

    public function edit(Automation $automation, GetAutomationEditorData $editorData): Response
    {
        $this->authorize('update', $automation);

        ['socialAccounts' => $socialAccounts, 'pinterestBoards' => $pinterestBoards, 'tiktokCreatorInfos' => $tiktokCreatorInfos] = $editorData($automation);

        $platformConfigs = $socialAccounts->mapWithKeys(fn ($account) => [
            $account->id => new PlatformConfigResource($account),
        ]);

        return Inertia::render('automations/Form', [
            'automation' => AutomationResource::make($automation),
            'socialAccounts' => SocialAccountResource::collection($socialAccounts),
            'platformConfigs' => $platformConfigs,
            'pinterestBoards' => $pinterestBoards,
            'tiktokCreatorInfos' => $tiktokCreatorInfos,
        ]);
    }

    public function show(Automation $automation, GetAutomationDetails $details): Response
    {
        $this->authorize('view', $automation);

        ['runs' => $runs, 'triggerItems' => $triggerItems] = $details($automation);

        return Inertia::render('automations/Show', [
            'automation' => AutomationResource::make($automation),
            'runs' => AutomationRunResource::collection($runs),
            'triggerItems' => AutomationTriggerItemResource::collection($triggerItems),
        ]);
    }

    public function update(UpdateAutomationRequest $request, Automation $automation, UpdateAutomation $update): RedirectResponse
    {
        $this->authorize('update', $automation);

        $update($automation, $request->validated());

        return back();
    }

    public function destroy(Automation $automation, DeleteAutomation $delete): RedirectResponse
    {
        $this->authorize('delete', $automation);
        $delete($automation);

        session()->flash('flash.banner', __('automations.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.automations.index');
    }

    public function activate(ActivateAutomationRequest $request, Automation $automation, ActivateAutomation $activate): RedirectResponse
    {
        $this->authorize('activate', $automation);

        $activate($automation);

        return back();
    }

    public function pause(PauseAutomationRequest $request, Automation $automation, PauseAutomation $pause): RedirectResponse
    {
        $this->authorize('pause', $automation);

        $pause($automation);

        return back();
    }

    public function retryRun(
        RetryRunRequest $request,
        RetryRunFromNode $retry,
        Automation $automation,
        AutomationRun $run,
    ): \Illuminate\Http\Response {
        $this->authorize('update', $automation);
        abort_unless($run->automation_id === $automation->id, 404);

        $nodeId = $request->validated('node_id') ?? $run->current_node_id;
        $retry($run, $nodeId);

        return response()->noContent();
    }

    public function test(TestAutomationRequest $request, Automation $automation, TestAutomation $test): JsonResponse
    {
        $this->authorize('update', $automation);

        $run = $test($automation, (bool) $request->validated('with_real_data', false));

        return response()->json(['run_id' => $run->id]);
    }

    public function showRun(Automation $automation, AutomationRun $run): JsonResponse
    {
        $this->authorize('view', $automation);
        abort_unless($run->automation_id === $automation->id, 404);

        $run->load('nodeRuns');

        return response()->json([
            'run' => AutomationRunResource::make($run)->resolve(),
            'node_runs' => AutomationNodeRunResource::collection($run->nodeRuns)->resolve(),
        ]);
    }
}
