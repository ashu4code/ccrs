{{-- Cascading organisation selects: DISCOM → Circle → Division → Subdivision → Section.
     Child options load via AJAX, scoped to the selected parent by FK relation. --}}
@props(['discoms', 'selected' => []])

<div id="discom-wrapper">
    <label for="discom_id" class="mb-1 block text-sm font-medium">DISCOM</label>
    <select name="discom_id" id="discom_id" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
        <option value="">Select DISCOM</option>
        @foreach ($discoms as $discom)
            <option value="{{ $discom->id }}" @selected((string) data_get($selected, 'discom_id') == (string) $discom->id)>{{ $discom->name }}</option>
        @endforeach
    </select>
</div>

<div id="circle-wrapper">
    <label for="circle_id" class="mb-1 block text-sm font-medium">Circle</label>
    <select name="circle_id" id="circle_id" data-selected="{{ data_get($selected, 'circle_id') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
        <option value="">Select Circle</option>
    </select>
</div>

<div id="division-wrapper">
    <label for="division_id" class="mb-1 block text-sm font-medium">Division</label>
    <select name="division_id" id="division_id" data-selected="{{ data_get($selected, 'division_id') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
        <option value="">Select Division</option>
    </select>
</div>

<div id="subdivision-wrapper">
    <label for="subdivision_id" class="mb-1 block text-sm font-medium">Subdivision</label>
    <select name="subdivision_id" id="subdivision_id" data-selected="{{ data_get($selected, 'subdivision_id') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
        <option value="">Select Subdivision</option>
    </select>
</div>

<div id="section-wrapper">
    <label for="section_id" class="mb-1 block text-sm font-medium">Section</label>
    <select name="section_id" id="section_id" data-selected="{{ data_get($selected, 'section_id') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
        <option value="">Select Section</option>
    </select>
</div>

<script>
    // Each level's options are fetched via AJAX according to the FK relation with
    // the selected parent: discom → circle → division → subdivision → section.
    (async () => {
        const levels = [
            { parent: 'discom_id', child: 'circle_id', param: 'discom_id', url: "{{ route('admin.hierarchy.circles') }}", placeholder: 'Select Circle' },
            { parent: 'circle_id', child: 'division_id', param: 'circle_id', url: "{{ route('admin.hierarchy.divisions') }}", placeholder: 'Select Division' },
            { parent: 'division_id', child: 'subdivision_id', param: 'division_id', url: "{{ route('admin.hierarchy.subdivisions') }}", placeholder: 'Select Subdivision' },
            { parent: 'subdivision_id', child: 'section_id', param: 'subdivision_id', url: "{{ route('admin.hierarchy.sections') }}", placeholder: 'Select Section' },
        ];

        const byId = (id) => document.getElementById(id);

        async function fetchOptions(config, parentValue) {
            const params = new URLSearchParams();
            params.set(config.param, parentValue);
            const response = await fetch(config.url + '?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) return [];
            const payload = await response.json();
            return Array.isArray(payload.data) ? payload.data : [];
        }

        function renderLevel(config, options, selected) {
            const select = byId(config.child);
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = config.placeholder;
            select.appendChild(placeholder);
            for (const option of options) {
                const el = document.createElement('option');
                el.value = option.id;
                el.textContent = option.name;
                el.selected = selected !== '' && String(selected) === String(option.id);
                select.appendChild(el);
            }
        }

        // Rebuild every level down the chain. With `keep` (initial load, e.g. the
        // edit page) each level's saved value is restored after its options are
        // fetched; on a user change deeper levels are refetched for the new
        // parent, which clears stale selections on its own.
        async function populateFrom(startIndex, keep) {
            for (let index = startIndex; index < levels.length; index++) {
                const config = levels[index];
                const parentValue = byId(config.parent).value;
                const selected = keep ? (byId(config.child).dataset.selected ?? '') : '';
                const options = parentValue ? await fetchOptions(config, parentValue) : [];
                renderLevel(config, options, selected);
            }
        }

        // DISCOM has no parent level of its own, so its change repopulates the
        // chain from the circle level; every other select repopulates one deeper.
        byId(levels[0].parent).addEventListener('change', () => populateFrom(0, false));
        levels.forEach((config, index) => {
            byId(config.child).addEventListener('change', () => populateFrom(index + 1, false));
        });

        await populateFrom(0, true);
    })();
</script>
