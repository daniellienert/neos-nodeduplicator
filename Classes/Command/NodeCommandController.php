<?php
namespace Flownative\NodeDuplicator\Command;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Validation\Validator\UuidValidator;
use Neos\Neos\Domain\Service\ContentContextFactory;

/**
 *
 */
class NodeCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contextFactory;

    /**
     * Adopt the given starting point and all child nodes recursively from the given dimension to the target dimensions.
     *
     * Dimensions can be given as a string with the format
     * <dimensionName>=<dimensionValue>[,<optionallyMultipleValues>][&<nextDimensionName=<dimensionValue>]
     *
     * @param string $startingPoint Either node UUID or node path
     * @param string $fromDimensions
     * @param string $toDimensions
     * @param string $workspace A workspace name, defaults to "live"
     */
    public function adoptCommand(string $startingPoint, string $fromDimensions, string $toDimensions, string $workspace = 'live'): void
    {
        $fromDimensionArray = NodePaths::parseDimensionValueStringToArray($fromDimensions);
        $toDimensionArray = NodePaths::parseDimensionValueStringToArray($toDimensions);

        $sourceContext = $this->createContext($workspace, $fromDimensionArray);
        $targetContext = $this->createContext($workspace, $toDimensionArray);

        if (preg_match(UuidValidator::PATTERN_MATCH_UUID, $startingPoint)) {
            $startingPointNode = $sourceContext->getNodeByIdentifier($startingPoint);
        } else {
            $startingPointNode = $sourceContext->getNode($startingPoint);
        }

        if ($startingPointNode === null) {
            $this->outputLine('Could not find given starting point to adopt nodes.');
            $this->quit(1);
        }

        $this->adoptToTargetContext($startingPointNode, $targetContext);

        $this->outputLine('');
        $this->outputLine('All nodes have been adopted. Done...');
    }

    /**
     * Create a context with the given workspace and dimensions
     *
     * @param string $workspaceName
     * @param array $dimensions
     * @return Context
     */
    protected function createContext(string $workspaceName, array $dimensions): Context
    {
        return $this->contextFactory->create([
            'workspaceName' => $workspaceName,
            'dimensions' => $dimensions
        ]);
    }

    /**
     * Adopt recursively to the targetContext.
     *
     * @param NodeInterface $startingPointNode
     * @param Context $targetContext
     * @return void
     */
    protected function adoptToTargetContext(NodeInterface $startingPointNode, Context $targetContext): void
    {
        $targetContext->adoptNode($startingPointNode);
        $this->outputLine(sprintf('Adopted Node "%s" to new dimensions.', $startingPointNode->getPath()));
        foreach ($startingPointNode->getChildNodes() as $childNode) {
            $this->adoptToTargetContext($childNode, $targetContext);
        }
    }
}
