<?php

namespace Lupennat\NestedMany\Fields;

use Illuminate\Http\Request;
use Laravel\Nova\Contracts\BehavesAsPanel;
use Laravel\Nova\Contracts\RelatableField;
use Laravel\Nova\Fields\Collapsable;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\SupportsDependentFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Http\Requests\ResourceCreateOrAttachRequest;
use Laravel\Nova\Panel;
use Lupennat\NestedMany\Exceptions\NotNestableModelException;
use Lupennat\NestedMany\Models\Contracts\Nestable;

abstract class Nested extends Field implements BehavesAsPanel, RelatableField
{
    use SupportsDependentFields;
    use Collapsable;
    use NestedPropagable;
    use NestedStorable;

    /**
     * Determines whether the children should be collapsed by default.
     */
    public bool $collapsedChildrenByDefault = false;

    /**
     * Can change view type.
     */
    public bool $canChangeViewType = false;

    /**
     * Style Relationship as Tabs.
     */
    public bool $useTabs = false;

    /**
     * Active Child.
     */
    public int|string $active = 0;

    /**
     * Default children.
     *
     * @var array<array<string,mixed>
     */
    public array $defaultChildren = [];

    /**
     * Mandatory Fields before create.
     *
     * @var array<string>
     */
    public array $hiddenFields = [];

    /**
     * Lock Add/Remove Children.
     */
    public bool $lock = false;

    /**
     * Make current field behaves as panel.
     *
     * @return \Laravel\Nova\Panel
     */
    public function asPanel()
    {
        return Panel::make($this->name, [$this])
            ->withMeta([
                'prefixComponent' => true,
            ])->withComponent('relationship-nested-panel');
    }

    /**
     * Set the children as collapsed by default.
     *
     * @return $this
     */
    public function collapsedChildrenByDefault()
    {
        $this->collapsedChildrenByDefault = true;

        return $this;
    }

    /**
     * Style Relationship as Tabs.
     *
     * @return $this
     */
    public function useTabs(bool $useTabs = true)
    {
        $this->useTabs = $useTabs;

        return $this;
    }

    /**
     * Set Active By Number.
     *
     * @return $this
     */
    public function active(int $active = 0)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Set Active By Title.
     *
     * @return $this
     */
    public function activeTitle(string|null $activeTitle = null)
    {
        $this->active = $activeTitle ?? 0;

        return $this;
    }

    /**
     * Show Change Layout Button.
     *
     * @return $this
     */
    public function canChangeViewType(bool $changeViewType = true)
    {
        $this->canChangeViewType = $changeViewType;

        return $this;
    }

    /**
     * Define default Children data.
     *
     * @param array<array<string<mixed>|\Illuminate\Database\Model>|(callable(\Laravel\Nova\Http\Requests\NovaRequest): (array<array<string<mixed>|\Illuminate\Database\Model>))
     *
     * @return $this
     */
    public function defaultChildren($children)
    {
        $this->defaultChildren = $children;

        return $this;
    }

    /**
     * Define Mandatory Fields Before Create.
     *
     * @param array<string>
     *
     * @return $this
     */
    public function hideFields(array $fields)
    {
        $this->hiddenFields = $fields;

        return $this;
    }

    /**
     * Min Children number.
     *
     * @return $this
     */
    public function min(int $min)
    {
        $this->min = $min;

        return $this;
    }

    /**
     * Lock Add/Remove Children.
     *
     * @return $this
     */
    public function lock(bool $lock = true)
    {
        $this->lock = $lock;

        return $this;
    }

    /**
     * Get defaultChildren.
     *
     * @return array<string>
     */
    protected function resolveDefaultChildren(NovaRequest $request): array
    {
        return collect(is_callable($this->defaultChildren) ? call_user_func($this->defaultChildren, $request) : $this->defaultChildren)->toArray();
    }

    /**
     * Determine if the field should be for the given request.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     */
    protected function authorizedToRelate(Request $request): bool
    {
        return $request->findResource()->authorizedToAdd($request, $this->resourceClass::newModel())
            && $this->resourceClass::authorizedToCreateNested($request);
    }

    /**
     * Get Related Key Name.
     */
    protected function resolvePrimaryKeyName(): string
    {
        return $this->resourceClass::newModel()->getKeyName();
    }

    /**
     * Model must extends Nestable.
     */
    protected function validateNestableModel()
    {
        $model = $this->resourceClass::newModel();
        if (!($model instanceof Nestable)) {
            throw new NotNestableModelException($model);
        }
    }

    /**
     * Model Support Soft Delete.
     */
    protected function hasNestedSoftDelete()
    {
        return $this->resourceClass::newModel()->hasNestedSoftDelete();
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return with(app(NovaRequest::class), function ($request) {
            return array_merge([
                'active' => $this->active,
                'authorizedToCreateNested' => $this->authorizedToRelate($request),
                'canChangeViewType' => $this->canChangeViewType,
                'collapsedChildrenByDefault' => $this->collapsedChildrenByDefault,
                'defaultChildren' => $this->resolveDefaultChildren($request),
                'hasNestedSoftDelete' => $this->hasNestedSoftDelete(),
                'hiddenFields' => $this->hiddenFields,
                'primaryKeyName' => $this->resolvePrimaryKeyName(),
                'lock' => $this->lock,
                'mode' => $request instanceof ResourceCreateOrAttachRequest ? 'create' : 'update',
                'useTabs' => $this->useTabs,
                'propagated' => $this->propagated,
            ], parent::jsonSerialize());
        });
    }
}
