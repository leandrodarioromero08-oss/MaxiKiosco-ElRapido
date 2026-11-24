<!-- ============================================================
     MODAL DE DETALLE DE PROMOCIÓN (compartido: index.php / productos.php)
     El contenido lo arma promo-publico.js a partir de window.PROMOS_DATA
     ============================================================ -->
<div class="modal-overlay-promo" id="modalPromoDetalle">
  <div class="modal-promo-box" role="dialog" aria-modal="true" aria-labelledby="modalPromoTitulo">
    <button type="button" class="modal-promo-cerrar" onclick="cerrarModalPromoDetalle()" aria-label="Cerrar detalle de la promoción"><?= icono('x', '', 16) ?></button>
    <div class="modal-promo-banner" id="modalPromoBanner"></div>
    <div class="modal-promo-body" id="modalPromoBody"></div>
  </div>
</div>

<form method="POST" action="carrito_agregar_promo.php" id="formAgregarPromo" style="display:none;">
<?= csrfCampo() ?>
  <input type="hidden" name="promocion_id" id="inputPromoId">
  <input type="hidden" name="cantidad" id="inputPromoCantidad">
  <input type="hidden" name="volver" value="<?= htmlspecialchars(basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>">
</form>
