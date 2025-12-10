<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutomationRequest;
use App\Http\Requests\UpdateAutomationRequest;
use App\Models\Automation;
use App\Models\AutomationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(): View
    {
        $automations = Automation::orderBy('name')->paginate(15);

        return view('admin.automations.index', compact('automations'));
    }

    public function create(): View
    {
        return view('admin.automations.create');
    }

    public function store(StoreAutomationRequest $request): RedirectResponse
    {
        Automation::create($request->validated());

        return redirect()->route('admin.automations.index')->with('status', 'Automation created.');
    }

    public function edit(Automation $automation): View
    {
        return view('admin.automations.edit', compact('automation'));
    }

    public function update(UpdateAutomationRequest $request, Automation $automation): RedirectResponse
    {
        $automation->update($request->validated());

        return redirect()->route('admin.automations.index')->with('status', 'Automation updated.');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $automation->delete();

        return redirect()->route('admin.automations.index')->with('status', 'Automation deleted.');
    }

    public function toggle(Automation $automation): RedirectResponse
    {
        $automation->update(['is_active' => ! $automation->is_active]);

        return back()->with('status', 'Automation status updated.');
    }

    public function logs(Automation $automation): View
    {
        $logs = $automation->logs()->latest('started_at')->paginate(20);

        return view('admin.automations.logs', compact('automation', 'logs'));
    }

    public function showLog(AutomationLog $log): View
    {
        $log->load('automation');

        return view('admin.automations.log_show', compact('log'));
    }
}
