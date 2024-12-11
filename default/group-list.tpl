{$objectList}

<div class="input-field col s6 xl5">
    <select name="objectgroup_selection" id="objectgroup_selection" multiple>
        <option value="" disabled selected>Choose objectgroups for membership</option>
        {html_options options=$allObjectGroups}
    </select>
    <label for="objectgoup_selection">Objectgroups</label>
</div>

<div class="input-field col s4 xl5">
    <select name="group_selection" id="group_selection" multiple>
        <option value="" disabled selected>Choose groups for membership</option>
        {html_options options=$allGroups}
    </select>
    <label for="group_selection">Groups</label>
</div>