<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bypass The Intent Classifier
    |--------------------------------------------------------------------------
    |
    | When true, an "out_of_scope" verdict from the IntentClassifier no longer
    | short-circuits the chat: the question still goes to the BusinessAgent.
    | The classifier keeps running so its verdict shows up in the logs and in
    | the debug trace. Audit only; keep it off for real users.
    |
    */

    'bypass_intent_classifier' => (bool) env('CHAT_BYPASS_INTENT_CLASSIFIER', false),

    /*
    |--------------------------------------------------------------------------
    | Debug Trace
    |--------------------------------------------------------------------------
    |
    | When true, every chat reply (the /api/chat JSON and app:ask-agent output)
    | includes a "debug" trace: the classifier's intent, whether it blocked the
    | question, the conversation that was resumed, and every tool call with its
    | arguments and full result (sub-agents and the similarity search included).
    | It exposes internals the leak guard normally hides, so never enable it in
    | production.
    |
    */

    'debug' => (bool) env('CHAT_DEBUG', false),

];
