<?php

namespace ScrnHQ\Cashier;

use Illuminate\Database\Eloquent\Relations;
use Laravel\Cashier\Billable as BaseBillable;

trait Billable
{
    use BaseBillable;

    /**
     * Get the subscriptions related to this model.
     *
     * @return Relations\HasMany
     */
    public function subscriptions(): Relations\HasMany
    {
        return $this->hasMany(Subscription::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the subscription items related to this model.
     *
     * @return Relations\HasManyThrough
     */
    public function subscriptionItems(): Relations\HasManyThrough
    {
        return $this->hasManyThrough(SubscriptionItem::class, Subscription::class)->orderBy('created_at', 'desc');
    }

    /**
     * Begin creating a new subscription.
     *
     * @param $subscription
     * @return SubscriptionBuilder
     */
    public function newSubscription($subscription, $plan): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $subscription, $plan);
    }

    /**
     * Determine if
     * @param string $subscription
     * @param null $plan
     * @return bool
     */
    public function subscribed($subscription = 'default', $plan = null)
    {
        $subscription = $this->subscription($subscription);

        if (is_null($subscription)) {
            return false;
        }

        if (is_null($plan)) {
            return $subscription->valid();
        }

      $plan = $this->subscriptionItems()->where('stripe_plan', $plan)->first();

        if ($subscription->valid() and !is_null($plan)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the Stripe model is actively subscribed to one of the given plans.
     *
     * @param  array|string  $plans
     * @param  string  $subscription
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = 'default')
    {
        $subscription = $this->subscription($subscription);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $plans as $plan) {
            if ($subscription->hasItem($plan)) {
                return true;
            }
        }

        return false;
    }

    public function addPlan($plan, $quantity = 1, $subscription = 'default', $prorate = true)
    {
        $subscription = $this->subscription($subscription);

        if (!is_null($subscription)) {
            return $subscription->addItem($plan, $prorate, $quantity);
        }

        return $this->newSubscription($subscription, $plan)->create();
    }
}