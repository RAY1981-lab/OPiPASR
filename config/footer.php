<?php $page_wrap_container = $page_wrap_container ?? true; ?>
<?php if ($page_wrap_container): ?>
  </div>
<?php endif; ?>
</main>

<?php $header_variant = $header_variant ?? (function_exists('is_logged_in') && is_logged_in() ? 'private' : 'public'); ?>
<?php if ($header_variant !== 'public'): ?>
  </div>
<?php endif; ?>

<footer class="site-footer" role="contentinfo">
  <div class="container footer-inner">
    <div class="footer-right">
      © <span id="year"></span> opipasr.ru <span class="footer-dot">•</span>
      Разработчик сайта: Старший преподаватель кафедры организации пожаротушения и проведения аварийно-спасательных работ, капитан внутренней службы <strong>Юрченко Роман Александрович</strong>
    </div>
  </div>
</footer>

<script src="/assets/main.js"></script>
<script src="/assets/calculators.js"></script>
</body>
</html>
