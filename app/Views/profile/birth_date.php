<div class="auth-container" style="max-width:480px">
    <div class="card">
        <div class="card-body">
            <div style="text-align:center;margin-bottom:1.5rem;">
                <i class="bi bi-calendar-heart" style="font-size:2.5rem;color:var(--primary);"></i>
                <h2 style="margin-top:0.5rem;">Date de naissance requise</h2>
                <p class="text-muted" style="font-size:0.9rem;">
                    Pour continuer, veuillez renseigner votre date de naissance.<br>
                    Cette information est nécessaire pour adapter les services bancaires à votre profil.
                </p>
            </div>
            <form method="POST" action="/profile/birth-date">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="birth_date" class="form-label">
                        <i class="bi bi-calendar3"></i> Date de naissance <span style="color:var(--danger)">*</span>
                    </label>
                    <input type="date" id="birth_date" name="birth_date"
                           class="form-control"
                           max="<?= date('Y-m-d') ?>"
                           min="<?= date('Y-m-d', strtotime('-120 years')) ?>"
                           required autofocus>
                    <div style="font-size:0.76rem;color:var(--text-muted);margin-top:4px;">
                        Format : JJ/MM/AAAA
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-check-lg"></i> Enregistrer et continuer
                </button>
            </form>
            <div style="text-align:center;margin-top:1rem;font-size:0.84rem;">
                <a href="/logout" style="color:var(--text-muted);">
                    <i class="bi bi-box-arrow-left"></i> Se déconnecter
                </a>
            </div>
        </div>
    </div>
</div>
