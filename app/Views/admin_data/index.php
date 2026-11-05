<div class="panel panel-default">
    <div class="panel-heading"><strong>Administrator Data Tools</strong></div>
    <div class="panel-body">
        <?php if (session('success')): ?><div class="alert alert-success"><?= esc(session('success')) ?></div><?php endif; ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif; ?>

        <?= form_open_multipart('admin_data/import', ['class' => 'form-inline']) ?>
        <label for="stock_file">Import stock Excel file</label>
        <input id="stock_file" type="file" name="stock_file" accept=".xls,.xlsx" required>
        <button class="btn btn-primary" type="submit">Import / Update Stock</button>
        <?= form_close() ?>

        <hr>
        <h4>Clear operational and stock data</h4>
        <p class="text-danger">
            This preserves users, roles, settings, tax configuration, and ZIMRA device data.
            It removes sales, receivings, expenses, inventory history, and stock items.
        </p>
        <?= form_open('admin_data/clear_operational') ?>
        <input type="text" name="confirmation" placeholder="CLEAR OPERATIONAL DATA" required>
        <button class="btn btn-danger" type="submit" onclick="return confirm('This cannot be undone. Continue?')">Clear Data</button>
        <?= form_close() ?>
    </div>
</div>
