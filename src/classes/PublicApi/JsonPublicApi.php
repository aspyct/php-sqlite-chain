<?php
class JsonPublicApi extends AbstractPublicApi {
    protected function parseRequest() : ApiRequest {
        $data = json_decode(file_get_contents('php://input'), true);

        // If the lastKnownSequenceNumber is not specified,
        // it means the client is not interrested in previous instructions.
        $lastKnownSequenceNumber = $data['lastKnownSequenceNumber'] ?? ApiRequest::DONT_RETURN_INSTRUCTIONS;

        $instructionJson = $this->getOrFail('instruction', $data);
        $instruction = new MutableInstruction();

        foreach ($instructionJson as $statementJson) {
            $statement = new MutableStatement();

            $sql = $this->getOrFail('sql', $statement);
            $statement->setSql($sql);
            
            $parameters = $this->getOrFail('parameters', $statement);
            foreach ($parameters as $key => $value) {
                $statement->addParameter($key, $value);
            }

            $instruction->addStatement($statement);
        }

        return new ImmutableApiRequest(
            $lastKnownSequenceNumber,
            $instruction
        );
    }

    protected function sendResponse(ApiResponse $response) : void {

    }

    protected function sendError(int $code, string $message, array $details) : void {

    }

    private function getOrFail(string $key, array $array) {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        else {
            throw new InvalidRequestException("Could not find expected key $key in the request payload.");
        }
    }
}
