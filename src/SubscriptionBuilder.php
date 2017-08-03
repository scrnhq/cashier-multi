<?php

namespace ScrnHQ\Cashier;

use Laravel\Cashier\SubscriptionBuilder as BaseSubscriptionBuilder;

class SubscriptionBuilder extends BaseSubscriptionBuilder
{
    protected $plans;

    /**
     * Create a new multi subscription builder instance.
     *
     * @param mixed $owner
     * @param string $name
     * @param string $plan
     */
    public function __construct($owner, $name, $plan)
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->addPlan($plan);
    }

    /**
     * Specify the quantity of the subscription.
     *
     * @deprecated
     * @param  int  $quantity
     * @return $this
     */
    public function quantity($quantity)
    {
        trigger_error("Deprecation error. Updates should be performed on subscription items.", E_USER_NOTICE);
    }

    /**
     * Add a Stripe plan to the Stripe model.
     *
     * @param string $plan
     * @param int $quantity
     * @return $this
     */
    public function addPlan(string $plan, $quantity = 1)
    {
        $this->plans[$plan] = $quantity;
        return $this;
    }

    /**
     * Remove a Stripe plan from the Stripe model.
     *
     * @param string $plan
     * @return $this
     */
    public function removePlan(string $plan)
    {
        unset($this->plans[$plan]);
        return $this;
    }

    /**
     * Create a new Stripe subscription.
     *
     * @param string|null $token
     * @param array $options
     * @return mixed
     */
    public function create($token = null, array $options = [])
    {
        $customer = $this->getStripeCustomer($token, $options);

        $stripeSubscription = $customer->subscriptions->create($this->buildPayload());

        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialExpires;
        }

        $subscription = $this->owner->subscriptions()->create([
            'name' => $this->name,
            'stripe_id' => $stripeSubscription->id,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);

        foreach ($stripeSubscription->items->data as $item) {
            $subscription->items()->create([
                'stripe_id' => $item['id'],
                'stripe_plan' => $item['plan']['id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return $subscription;
    }

    /**
     * Build the payload for subscription creation.
     *
     * @return array
     */
    protected function buildPayload()
    {
        return array_filter([
            'items' => $this->buildPayloadItems(),
            'coupon' => $this->coupon,
            'trial_end' => $this->getTrialEndForPayload(),
            'tax_percent' => $this->getTaxPercentageForPayload(),
            'metadata' => $this->metadata,
        ]);
    }

    /**
     * Build the payload for subscription items creation.
     *
     * @return array
     */
    protected function buildPayloadItems()
    {
        $items = [];
        foreach ($this->plans as $plan => $quantity)
        {
            array_push($items, [
                'plan' => $plan,
                'quantity' => $quantity,
            ]);
        }
        return $items;
    }
}