<?php

declare(strict_types=1);

namespace Domain\Pricing;

/**
 * Applies pricing rules in a fixed, explicit order, starting from the space's base price.
 * Order matters: a percentage discount applied after a surcharge discounts the surcharge too.
 * See docs/adr/0004-pricing-and-cancellation-as-strategies.md.
 */
final readonly class PricingPipeline
{
    /** @var list<PricingRule> */
    private array $rules;

    public function __construct(PricingRule ...$rules)
    {
        $this->rules = array_values($rules);
    }

    public function quote(PricingContext $context): Quote
    {
        $quote = Quote::startingAt($context->space->basePriceFor($context->slot));

        foreach ($this->rules as $rule) {
            $quote = $rule->apply($quote, $context);
        }

        return $quote;
    }
}
