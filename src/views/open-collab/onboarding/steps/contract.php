<h2>Review & Sign Contract</h2>
<div class="contract-box"
     style="height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 1rem; margin-bottom: 1rem; background: #fff;">
    <?= $contract->content // Rendered as-is from ContractRepository  ?>
</div>

<form onsubmit="event.preventDefault(); window.Onboarding.submitContract(this)">
    <input type="hidden" name="contract_id" value="<?= $contract->id ?>">
    <label style="display: block; margin-bottom: 1rem;">
        <input type="checkbox" name="acknowledge" required>
        I have read and agree to the terms of this contract.
    </label>
    <button type="submit" class="btn-primary">Sign and Continue</button>
</form>