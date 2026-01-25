<?php

namespace App\Http\Controllers;

use App\Services\MobileEntry\DateContextBuilder;
use App\Services\MobileEntry\SessionMessageService;
use App\Services\MobileEntry\ComponentAssembler;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Abstract base controller for mobile entry pages
 * Implements Template Method pattern to eliminate duplication
 */
abstract class AbstractMobileEntryController extends Controller
{
    public function __construct(
        protected DateContextBuilder $dateContextBuilder,
        protected SessionMessageService $sessionMessageService,
        protected ComponentAssembler $componentAssembler
    ) {}
    
    /**
     * Template method - defines the algorithm structure
     * Subclasses implement hooks to customize behavior
     */
    protected function renderEntryPage(Request $request, string $entryType): View
    {
        // Step 1: Build date context (shared)
        $dateContext = $this->dateContextBuilder->build($request->all());
        
        // Step 2: Extract session messages (shared)
        $sessionMessages = $this->sessionMessageService->extract();
        
        // Step 3: Get service data (hook - varies by type)
        $serviceData = $this->getServiceData($request, $dateContext, $sessionMessages, $entryType);
        
        // Step 4: Build components (shared with hooks)
        $components = $this->componentAssembler->assemble(
            entryType: $entryType,
            dateContext: $dateContext,
            serviceData: $serviceData,
            config: $this->getComponentConfig($request, $entryType)
        );
        
        // Step 5: Build final view data (hook - varies by type)
        $data = $this->buildViewData($components, $serviceData, $entryType, $dateContext);
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Get component configuration
     * Can be overridden by subclasses for custom behavior
     */
    protected function getComponentConfig(Request $request, string $entryType): array
    {
        return [
            'shouldExpandSelection' => $request->boolean('expand_selection'),
            'isFirstTimeUser' => $this->isFirstTimeUser($entryType),
            'showAddButton' => $this->shouldShowAddButton($request, $entryType),
            'showItemList' => $this->shouldShowItemList($entryType),
        ];
    }
    
    /**
     * Build final view data
     * Can be overridden for custom data
     */
    protected function buildViewData(array $components, array $serviceData, string $entryType, array $dateContext): array
    {
        return [
            'components' => $components,
            'autoscroll' => $this->shouldAutoscroll($entryType),
        ];
    }
    
    // Hooks that subclasses must implement
    abstract protected function getServiceData(
        Request $request,
        array $dateContext,
        array $sessionMessages,
        string $entryType
    ): array;
    
    // Hooks with default implementations (can be overridden)
    protected function isFirstTimeUser(string $entryType): bool
    {
        return false;
    }
    
    protected function shouldShowAddButton(Request $request, string $entryType): bool
    {
        return !$request->boolean('expand_selection');
    }
    
    protected function shouldShowItemList(string $entryType): bool
    {
        return $entryType !== 'measurements';
    }
    
    protected function shouldAutoscroll(string $entryType): bool
    {
        return $entryType !== 'measurements';
    }
}
