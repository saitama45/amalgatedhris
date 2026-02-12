<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FaceService
{
    /**
     * This service now handles the logic for storing and potentially 
     * validating facial data, although the actual recognition
     * happens in the browser for shared hosting compatibility.
     */

    public function compareEmbeddings(array $embedding1, array $embedding2): float
    {
        // Cosine similarity
        $dotProduct = 0;
        $mag1 = 0;
        $mag2 = 0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $mag1 += $embedding1[$i] ** 2;
            $mag2 += $embedding2[$i] ** 2;
        }

        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);

        if ($mag1 == 0 || $mag2 == 0) return 0;

        return $dotProduct / ($mag1 * $mag2);
    }
}
