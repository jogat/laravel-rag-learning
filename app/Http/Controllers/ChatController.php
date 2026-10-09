<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\BusinessAssistant;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, BusinessAssistant $assistant)
    {
        $data = $request->validate([
            'project' => 'required|string|exists:projects,slug',
            'message' => 'required|string|max:2000',
        ]);

        $project = Project::where('slug', $data['project'])->firstOrFail();
        $user = $request->user();

        return response()->json($assistant->ask($project, $user, $data['message']));
    }
}
