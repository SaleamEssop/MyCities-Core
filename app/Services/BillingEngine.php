<?php
namespace App\Services;

class BillingEngine
{
    public function calculateCharge($a,$b,$c){return (object)['tieredCharge'=>0];}
    public function process($a,$p){return ['success'=>false];}
    public function reconcileProvisionalPeriods($a,$r){return ['success'=>false];}
}