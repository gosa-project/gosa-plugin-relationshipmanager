<div class="mainlist-header">
  <h2>{$HEADLINE}&nbsp;{$SIZELIMIT}</h2>
  <input type="hidden" name="ignore">
  <div class="control-panel">
    <div class="ldap-tree valign-wrapper">{$BASE}</div>
    <div class="actions center-align">{$ACTIONS}</div>
  </div>
</div>

<div class="list-content-wrapper">
  <div class="plus-actions">
    {$LIST}
  </div>

  <div class="card-action">
      <button class="btn-small primary" type="submit" name="ok-save">{msgPool type=okButton}</button>
      <button class="btn-small primary" type="submit" name="cancel-abort">{msgPool type=cancelButton}</button>
  </div>
</div>
