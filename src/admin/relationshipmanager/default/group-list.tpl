{$objectList}

<div class="row">
    <div class="input-field col s6 xl5">
        <select name="object_group_selection[]" id="objectgroup_selection" multiple>
            {html_options options=$objectGroups}
        </select>
        <label for="objectgroup-selection">{t}Objectgroup{/t}</label>
    </div>
    
    <div class="input-field col s6 xl5">
        <select name="posix_group_selection[]" id="posixgroup_selection" multiple>
            {html_options options=$posixGroups}
        </select>
        <label for="posixgroup-selection">{t}Group{/t}</label>
    </div>
</div>


<!-- TODO: Autocomplete have no option for multiselect!!!
<input type="text" id="object-group-input" class="autocomplete">
<input type="text" id="posix-group-input" class="autocomplete">
{literal}
<script language="JavaScript" type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        var elems = document.getElementById('object-group-input');
        var instances = M.Autocomplete.init(elems, {
            isMultiselect: true,
            data: {
                "Apple": null,
                "Microsoft": null,
            },

            onSearch: (text, autocomplete) => {
                const filteredData = autocomplete.options.data.filter(item => {
                    return Object.keys(item)
                        .map(key => item[key].toString().toLowerCase().indexOf(text.toLowerCase()) >= 0)
                        .some(isMatch => isMatch);
                });
                autocomplete.setMenuItems(filteredData);
            }
        })
    })
</script>
{/literal} -->