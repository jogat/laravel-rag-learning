<?php

use App\Models\Project;
use App\Models\User;
use Laravel\Dusk\Browser;

/**
 * Answer POST /api/chat in the page instead of on the server.
 *
 * Dusk drives a separate server process, so the agent fakes used by the feature tests cannot reach
 * it, and the real chat needs Ollama. The backend side of /api/chat is covered by ChatControllerTest;
 * these tests cover what the React chat does with the response. Each request body is kept in
 * `window.chatRequests` so a test can assert what the page sent.
 *
 * @param  array<string, mixed>  $body
 */
function stubChatResponse(Browser $browser, int $status, array $body): void
{
    $browser->script(sprintf(<<<'JS'
        window.chatRequests = [];
        const realFetch = window.fetch;
        window.fetch = (url, options = {}) => {
            if (String(url).endsWith('/api/chat')) {
                window.chatRequests.push(JSON.parse(options.body));
                return Promise.resolve(new Response(%s, {status: %d, headers: {'Content-Type': 'application/json'}}));
            }
            return realFetch(url, options);
        };
    JS, json_encode(json_encode($body)), $status));
}

function openChat(Browser $browser, User $user, string $projectSlug): Browser
{
    return $browser->loginAs($user)
        ->visit('/chat')
        ->waitFor("select option[value={$projectSlug}]");
}

it('sends the message for the selected project and shows the reply', function () {
    $user = User::factory()->create();
    Project::factory()->create(['name' => 'Alpha Shop', 'slug' => 'alpha']);

    $this->browse(function (Browser $browser) use ($user) {
        openChat($browser, $user, 'alpha');
        stubChatResponse($browser, 200, ['reply' => 'We open at 9.', 'conversation_id' => null]);

        $browser->type('input[placeholder="Ask your question…"]', 'What are your hours?')
            ->press('Submit')
            ->waitForText('We open at 9.')
            ->assertSee('What are your hours?')
            ->assertInputValue('input[placeholder="Ask your question…"]', '');

        expect($browser->script('return window.chatRequests;')[0])
            ->toEqual([['project' => 'alpha', 'message' => 'What are your hours?']]);
    });
});

it('shows the server error message when the chat request fails', function () {
    $user = User::factory()->create();
    Project::factory()->create(['slug' => 'alpha']);

    $this->browse(function (Browser $browser) use ($user) {
        openChat($browser, $user, 'alpha');
        stubChatResponse($browser, 500, ['message' => 'Server Error']);

        $browser->type('input[placeholder="Ask your question…"]', 'What are your hours?')
            ->press('Submit')
            ->waitForText('Server Error')
            ->assertButtonEnabled('Submit');
    });
});

it('clears the conversation when another project is selected', function () {
    $user = User::factory()->create();
    Project::factory()->create(['name' => 'Alpha Shop', 'slug' => 'alpha']);
    Project::factory()->create(['name' => 'Beta Shop', 'slug' => 'beta']);

    $this->browse(function (Browser $browser) use ($user) {
        openChat($browser, $user, 'beta');
        stubChatResponse($browser, 200, ['reply' => 'We open at 9.', 'conversation_id' => null]);

        $browser->type('input[placeholder="Ask your question…"]', 'What are your hours?')
            ->press('Submit')
            ->waitForText('We open at 9.')
            ->select('select', 'beta')
            ->waitUntilMissingText('We open at 9.')
            ->assertDontSee('What are your hours?');
    });
});
