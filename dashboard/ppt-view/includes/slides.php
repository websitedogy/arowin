<?php
/**
 * PPT View — business presentation slides
 */
declare(strict_types=1);

/**
 * @return list<array{title:string, eyebrow:string, body:string, points:list<string>}>
 */
function arowin_ppt_slides(): array
{
    return [
        [
            'eyebrow' => 'Arowin Trading Portal',
            'title' => 'Build. Grow. Earn.',
            'body' => 'A USDT-powered binary network with clear packages and transparent wallet tools.',
            'points' => [
                'Secure operator dashboard',
                'Wallet deposit · transfer · withdraw',
                'Lifetime package activation with power IDs',
            ],
        ],
        [
            'eyebrow' => 'Packages',
            'title' => 'Choose your node package',
            'body' => 'Pay once from your Master Wallet. One lifetime package per main ID.',
            'points' => [
                '$50 · $150 · $350 · $750',
                '$1550 · $3150 · $6350 · $12750',
                'Eligible packages show green when wallet can afford them',
            ],
        ],
        [
            'eyebrow' => 'Activation',
            'title' => 'Self $50 + Left / Right power IDs',
            'body' => 'After purchase, your ID stays as a $50 unit. Remaining units split equally left and right.',
            'points' => [
                '$150 → 1 left + 1 right',
                '$350 → 3 left + 3 right',
                '$750 → 7 left + 7 right',
            ],
        ],
        [
            'eyebrow' => 'Binary plan',
            'title' => 'Left team · Right team',
            'body' => 'Place members under your binary tree. Track both legs from Genealogy and Team pages.',
            'points' => [
                'Genealogy tree for structure',
                'Left / Right team lists',
                'Power IDs for auto-ID login',
            ],
        ],
        [
            'eyebrow' => 'Wallet',
            'title' => 'Master Wallet flow',
            'body' => 'Every fund movement is saved forever in History — like a payment app statement.',
            'points' => [
                'Deposit USDT to wallet',
                'Transfer to another ARW ID',
                'Withdraw · buy package · download CSV history',
            ],
        ],
        [
            'eyebrow' => 'Grow faster',
            'title' => 'Invite with your ID',
            'body' => 'Share your sponsor link, track guests, and convert them into active operators.',
            'points' => [
                'Guest List for prospects',
                'Direct joiners under your sponsor ID',
                'Register → Package → Activate',
            ],
        ],
    ];
}
