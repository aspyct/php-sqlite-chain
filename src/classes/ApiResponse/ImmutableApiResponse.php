<?php
class ImmutableApiResponse implements ApiResponse {
    private $missingInstructions;

    public function __construct(array $missingInstructions) {
        $this->missingInstructions = $missingInstructions;
    }

    public function getMissingInstructions() : array {
        return $this->missingInstructions;
    }
}
