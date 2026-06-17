<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HelmetDetectionController extends ApiController
{
    private const HELMET_LABELS = [
        'helmet', 'hard hat', 'safety helmet', 'motorcycle helmet',
        'bicycle helmet', 'crash helmet', 'protective headgear',
    ];

    /** POST /v1/driver/helmet-check */
    public function check(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $request->validate(['image' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120']);

        $imageData = base64_encode(file_get_contents($request->file('image')->getRealPath()));
        $apiKey    = config('services.google_vision.key');

        $response = Http::post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
            'requests' => [[
                'image'    => ['content' => $imageData],
                'features' => [
                    ['type' => 'LABEL_DETECTION',  'maxResults' => 20],
                    ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 10],
                ],
            ]],
        ]);

        if (! $response->successful()) {
            return response()->json(['data' => null, 'message' => 'Vision API error.'], 502);
        }

        $annotations = $response->json('responses.0.labelAnnotations', []);
        $objects     = $response->json('responses.0.localizedObjectAnnotations', []);

        $helmetLabel  = null;
        $confidence   = 0.0;

        foreach ($annotations as $label) {
            $desc = strtolower($label['description'] ?? '');
            foreach (self::HELMET_LABELS as $keyword) {
                if (str_contains($desc, $keyword)) {
                    if (($label['score'] ?? 0) > $confidence) {
                        $confidence  = $label['score'];
                        $helmetLabel = $label['description'];
                    }
                }
            }
        }

        foreach ($objects as $obj) {
            $name = strtolower($obj['name'] ?? '');
            foreach (self::HELMET_LABELS as $keyword) {
                if (str_contains($name, $keyword)) {
                    $score = $obj['score'] ?? 0;
                    if ($score > $confidence) {
                        $confidence  = $score;
                        $helmetLabel = $obj['name'];
                    }
                }
            }
        }

        $passed = $confidence >= 0.60;

        return $this->success([
            'passed'     => $passed,
            'confidence' => round($confidence, 3),
            'label'      => $helmetLabel,
            'labels'     => array_map(fn ($l) => [
                'description' => $l['description'],
                'score'       => round($l['score'] ?? 0, 3),
            ], array_slice($annotations, 0, 5)),
        ], $passed ? 'Helmet detected.' : 'No helmet detected.');
    }
}
