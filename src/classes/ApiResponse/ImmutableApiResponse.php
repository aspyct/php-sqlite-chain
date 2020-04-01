<?php
class ImmutableApiResponse implements ApiResponse {
    private $missingInstructions;

    public function __construct(array $missingInstructions) {
        $this->missingInstructions = $missingInstructions;
    }

    public function listMissingInstructions() : array {
        return $this->missingInstructions;
    }
}
