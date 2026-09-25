<?php
declare(strict_types=1);
?>
<footer class="site-footer" id="sign-in">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand-block">
        <h2>Driving the Future of Binary Yield</h2>
        <p>
          Arowin supports and empowers growing operator networks across the blockchain —
          a USDT-native trading portal built for institutional binary matching and high-precision node validation.
        </p>
      </div>

      <div class="footer-columns">
        <div class="footer-col">
          <h3>Resources</h3>
          <ul>
            <li><a href="<?= e(url('usdt-matching')) ?>">USDT Matching</a></li>
            <li><a href="<?= e(url('faqs')) ?>">FAQs</a></li>
            <li><a href="<?= e(url('binary-plan')) ?>">Binary Plan Guide</a></li>
            <li><a href="<?= e(url('yield-estimator')) ?>">Yield Estimator</a></li>
            <li><a href="<?= e(url('blog')) ?>">Blog</a></li>
            <li><a href="<?= e(url('news')) ?>">News</a></li>
            <li><a href="<?= e(url('about')) ?>">Arowin Facts</a></li>
            <li><a href="<?= e(url('contact')) ?>">Support Channels</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h3>Arowin</h3>
          <ul>
            <li><a href="<?= e(url('about')) ?>">Why Arowin?</a></li>
            <li><a href="<?= e(url('binary-plan')) ?>">How It Works</a></li>
            <li><a href="<?= e(url('faqs')) ?>">Knowledge Base</a></li>
            <li><a href="<?= e(url('products')) ?>">Products</a></li>
            <li><a href="<?= e(url('videos')) ?>">Videos</a></li>
            <li><a href="<?= e(url('downloads')) ?>">Downloads</a></li>
            <li><a href="<?= e(url('legal-terms')) ?>">Transparency</a></li>
            <li><a href="<?= e(url('usdt-matching')) ?>">Fees &amp; Caps</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h3>Products</h3>
          <ul>
            <li><a href="<?= e(url('register')) ?>">Core Node Registration</a></li>
            <li><a href="<?= e(url('usdt-matching')) ?>">USDT Matching Plan</a></li>
            <li><a href="<?= e(url('yield-estimator')) ?>">Interactive Yield Terminal</a></li>
            <li><a href="<?= e(url('binary-plan')) ?>">Ledger Sync Engine</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h3>Solutions</h3>
          <ul>
            <li><a href="<?= e(url('register')) ?>">For Individuals</a></li>
            <li><a href="<?= e(url('about')) ?>">For Operators</a></li>
            <li><a href="<?= e(url('binary-plan')) ?>">For Networks</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h3>Company</h3>
          <ul>
            <li><a href="<?= e(url('about')) ?>">About Us</a></li>
            <li><a href="<?= e(url('careers')) ?>">Careers</a></li>
            <li><a href="<?= e(url('contact')) ?>">Contact Us</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h3>Legal</h3>
          <ul>
            <li><a href="<?= e(url('legal-terms')) ?>">Legal Terms</a></li>
            <li><a href="<?= e(url('cookie-settings')) ?>">Cookie Settings</a></li>
          </ul>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <a class="footer-logo" href="<?= e(url('home')) ?>" aria-label="<?= e($APP['name']) ?> home">
        <span class="footer-logo-mark" aria-hidden="true">
          <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M14 2.5L25.5 14L14 25.5L2.5 14L14 2.5Z" stroke="#fff" stroke-width="1.6"/>
            <path d="M14 7L21 14L14 21L7 14L14 7Z" fill="#fff"/>
          </svg>
        </span>
        <?= e(strtolower($APP['short_name'])) ?>
      </a>

      <p class="footer-copy">Copyright &copy; <?= e($COPY_RANGE) ?> <?= e($APP['legal_name'] ?? $APP['name']) ?>. All rights reserved.</p>

      <nav class="footer-social" aria-label="Social media">
        <a href="<?= e($APP['social']['x']) ?>" target="_blank" rel="noopener noreferrer" aria-label="X">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.286-6.914L5.04 21.75H1.73l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        </a>
        <a href="<?= e($APP['social']['instagram']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
        </a>
        <a href="<?= e($APP['social']['youtube']) ?>" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31.5 31.5 0 0 0 0 12a31.5 31.5 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.5 31.5 0 0 0 24 12a31.5 31.5 0 0 0-.5-5.8zM9.75 15.5v-7l6.5 3.5-6.5 3.5z"/></svg>
        </a>
        <a href="<?= e($APP['social']['telegram']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Telegram">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3 2.8 11.6c-1.3.5-1.3 1.2-.2 1.5l4.9 1.5 1.9 5.8c.2.7.4.9 1 .9.6 0 .8-.3 1.1-.6l2.7-2.6 5.6 4.1c1 .6 1.8.3 2-.9L23.7 5.7c.3-1.3-.5-1.9-1.8-1.4zM8.9 14.3l9.8-6.2c.5-.3 1-.1.6.2l-8 7.2-.3 3.3-2.1-4.5z"/></svg>
        </a>
        <a href="<?= e($APP['social']['linkedin']) ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5C4.98 4.88 3.86 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.5 8.5h4V23h-4V8.5zM8.5 8.5h3.8v2h.1c.5-1 1.8-2.1 3.7-2.1 4 0 4.7 2.6 4.7 6V23h-4v-6.6c0-1.6 0-3.6-2.2-3.6s-2.5 1.7-2.5 3.5V23h-4V8.5z"/></svg>
        </a>
        <a href="<?= e($APP['social']['facebook']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H7v3h3v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1z"/></svg>
        </a>
        <a href="<?= e($APP['social']['reddit']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Reddit">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14.2 3.2a1.2 1.2 0 0 0-1 .6l-1.7.4a6.8 6.8 0 0 0-4.3 2.8 3.3 3.3 0 0 0-4.5 3 3.3 3.3 0 0 0 1.8 3c0 .3-.1.7-.1 1 0 3.4 3.7 6.1 8.3 6.1s8.3-2.7 8.3-6.1c0-.3 0-.7-.1-1a3.3 3.3 0 1 0-3.4-5.5 6.8 6.8 0 0 0-3.3-1.3zm-.9 1.7.5-.1.9 2.1c-.5 0-.9.1-1.4.1zm-2.6 4.8a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4zm5.6 0a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4zm-7 3.5c.4 1.5 2.1 2.6 4.2 2.6s3.8-1.1 4.2-2.6c-.8.5-2.3.9-4.2.9s-3.4-.4-4.2-.9z"/></svg>
        </a>
      </nav>
    </div>
  </div>
</footer>
