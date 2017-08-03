# Cashier-Multi
[![CircleCI](https://circleci.com/gh/scrnhq/cashier-multi/tree/master.svg?style=svg)](https://circleci.com/gh/scrnhq/cashier-multi/tree/master)

## Introduction

Laravel Cashier provides an expressive, fluent interface to [Stripe's](https://stripe.com) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier can handle coupons, swapping subscription, subscription "quantities", cancellation grace periods, and even generate invoice PDFs.

## Running Cashier's Tests Locally

You will need to set the following details locally and on your Stripe account in order to run the Cashier unit tests:

### Environment

#### .env

    STRIPE_KEY=
    STRIPE_SECRET=
    STRIPE_MODEL=ScrnHQ\Cashier\Tests\Fixtures\User

### Stripe

#### Plans

    * monthly-10-1 ($10)
    * monthly-10-2 ($10)

#### Coupons

    * coupon-1 ($5)
