<div class="field">
    <label for="privilege">Device role</label>
    <select id="privilege" name="privilege" required>
        @foreach ($privilegeOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('privilege', $selectedPrivilege ?? 'User') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <p class="muted" style="margin:0.35rem 0 0; font-size:0.8rem; font-weight:400;">
        ZkTeco privilege on this terminal (who can manage users or open device menu).
    </p>
    @error('privilege')<p class="errors">{{ $message }}</p>@enderror
</div>
