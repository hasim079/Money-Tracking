<?php
// Bu dosya güvenlik ve yönlendirme amaçlıdır.
// Tüm trafiği güvenli "public" klasörüne yönlendirir.
header("Location: public/auth.php");
exit();
?>
