<?php
abstract class AbstractPublicApi implements PublicApi {
    private $choreographer;

    public function __construct(Choreographer $choreographer) {
        $this->choreographer = $choreographer;
    }

    /**
     * @throw InvalidRequestException
     */
    protected abstract function parseRequest() : ApiRequest;
    protected abstract function sendResponse(ApiResponse $response) : void;
    protected abstract function sendError(ApiError $error) : void;

    public function handleRequest() : void {
        $request = $this->parseRequest();

        $this->runInstruction($request);
        $missingInstructions = $this->getMissingInstructions($request);

        $response = new ImmutableApiResponse($missingInstructions);
        $this->sendResponse($response);

        // TODO Catch the catchable exceptions here
        // Let the rest crash the app
    }

    private function runInstruction(ApiRequest $request) : void {
        $instruction = $request->getInstruction();
        $this->choreographer->runInstruction($instruction);
    }

    private function getMissingInstructions(ApiRequest $request) : array {
        $lastKnownSequenceNumber = $request->getLastKnownSequenceNumber();

        if ($lastKnownSequenceNumber === -1) {
            // -1 means that our caller is not interrested in previous instructions
            $missingInstructions = [];
        }
        else {
            $missingInstructions = $choreographer->getInstructionsSince($lastKnownSequenceNumber);
        }

        return $missingInstructions;
    }
}
