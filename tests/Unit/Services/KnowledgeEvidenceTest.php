<?php

use App\Services\KnowledgeEvidence;

it('keeps the matching day sentence without unrelated hours', function (string $question, string $excerpt, string $expected) {
    expect((new KnowledgeEvidence)->forQuestion([$excerpt], $question))->toBe($expected);
})->with([
    'Spanish' => ['¿Y los domingos?', 'Los sábados abrimos de 10am a 2pm. Cerramos los domingos y días festivos.', 'Cerramos los domingos y días festivos.'],
    'English' => ['And on Sundays?', 'On Saturdays we open from 10am to 2pm. We are closed on Sundays.', 'We are closed on Sundays.'],
    'French' => ['Et le dimanche?', 'Le samedi, nous sommes ouverts de 10h à 14h. Nous sommes fermés le dimanche.', 'Nous sommes fermés le dimanche.'],
]);

it('keeps the express fee evidence without treating another shipping threshold as its price', function () {
    expect((new KnowledgeEvidence)->forQuestion([
        'El envío es gratis en compras mayores a 50 dólares. El envío exprés está disponible por un costo adicional.',
    ], '¿Cuánto cuesta el envío exprés?'))->toBe('El envío exprés está disponible por un costo adicional.');
});

it('retains retrieved excerpts when the question has no matching keyword', function () {
    expect((new KnowledgeEvidence)->forQuestion(['Abrimos de 9 a 18.'], '¿Cuál es el horario?'))->toBe('Abrimos de 9 a 18.');
});

it('reports unavailable evidence when retrieval returns no excerpts', function () {
    expect((new KnowledgeEvidence)->forQuestion([], '¿Cuál es el horario?'))->toBe('No relevant results found.');
});

it('does not repeat sentences present in overlapping chunks', function () {
    expect((new KnowledgeEvidence)->forQuestion([
        'Cerramos los domingos. Hay estacionamiento.',
        'Cerramos los domingos. Aceptamos tarjetas.',
    ], '¿Y los domingos?'))->toBe('Cerramos los domingos.');
});

it('preserves all retrieved facts when the question asks about multiple topics', function (string $question) {
    expect((new KnowledgeEvidence)->forQuestion([
        'Our store is open from 9am to 6pm. We accept credit cards.',
        'We are closed on Sundays.',
    ], $question))->toBe("Our store is open from 9am to 6pm. We accept credit cards.\nWe are closed on Sundays.");
})->with(['What are your hours and payment methods?', '¿Cuál es el horario y cuáles son los métodos de pago?', 'Quels sont les horaires et les moyens de paiement?']);

it('keeps opening and closing facts together without unrelated support hours', function (string $question, string $excerpt, string $expected) {
    expect((new KnowledgeEvidence)->forQuestion([$excerpt], $question))->toBe($expected);
})->with([
    'Spanish' => ['¿Cuál es el horario?', 'Nuestra tienda abre de 9am a 6pm. Cerramos los domingos. La atención al cliente está disponible de 8am a 8pm.', "Nuestra tienda abre de 9am a 6pm.\nCerramos los domingos."],
    'English' => ['What are your opening hours?', 'Our store is open from 9am to 6pm. We are closed on Sundays. Customer support is available from 8am to 8pm.', "Our store is open from 9am to 6pm.\nWe are closed on Sundays."],
    'French' => ['Quels sont les horaires?', 'Notre magasin est ouvert de 9h à 18h. Nous sommes fermés le dimanche. Le service client est disponible de 8h à 20h.', "Notre magasin est ouvert de 9h à 18h.\nNous sommes fermés le dimanche."],
]);

it('keeps support hours when the question explicitly asks about customer support', function (string $question, string $excerpt, string $expected) {
    expect((new KnowledgeEvidence)->forQuestion([$excerpt], $question))->toBe($expected);
})->with([
    'Spanish' => ['¿Cuál es el horario de atención al cliente?', 'Nuestra tienda abre de 9am a 6pm. La atención al cliente está disponible de 8am a 8pm.', 'La atención al cliente está disponible de 8am a 8pm.'],
    'English' => ['What are the customer service hours?', 'Our store is open from 9am to 6pm. Customer support is available from 8am to 8pm.', 'Customer support is available from 8am to 8pm.'],
    'French' => ['Quels sont les horaires du service client?', 'Notre magasin est ouvert de 9h à 18h. Le service client est disponible de 8h à 20h.', 'Le service client est disponible de 8h à 20h.'],
]);
