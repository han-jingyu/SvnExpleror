/* Common */

/**
 * 为 HTML 对象的 class 属性添加一个类名。
 *
 * 该函数在不改变 HTML 对象 class 属性现有值的情况下为其添加一个新的类。如新的类已存在则不在重复添加。
 *
 * @param {object} oElement - HTML 对象。
 * @param {string} sClassName - 类名称。
 */
function addClass(oElement, sClassName) {
    var sOldClassName = " " + oElement.className + " ";
    if (sOldClassName.indexOf(" " + sClassName + " ") == -1) {
        oElement.className = (oElement.className + " " + sClassName).trim();
    }
}

/**
 * 删除 HTML 对象 class 属性中的一个类名。
 *
 * 该函数从 HTML 对象 class 属性的现有类中删除指定的类名称。如要删除的类名称不存在则不执行删除。
 *
 * @param {object} oElement - HTML 对象。
 * @param {string} sClassName - 类名称。
 */
function delClass(oElement, sClassName) {
    var sOldClassName = " " + oElement.className + " ";
    var iPos = sOldClassName.indexOf(" " + sClassName + " ");
    if (iPos != -1) {
        oElement.className =
            (sOldClassName.substring(0, iPos) + sOldClassName.substring(iPos + sClassName.length + 1)).trim();
    }
}

/**
 * 判断 HTML 对象 class 属性是否包含某个类名称。
 *
 * 该函数查询 HTML 对象 class 属性的现有类，确定是否包含指定的类名称。
 *
 * @param {object} oElement - HTML 对象。
 * @param {string} sClassName - 类名称。
 * @returns {boolean} - 包含指定的类名称则返回真，否则返回假。
 */
function hasClass(oElement, sClassName) {
    return (" " + oElement.className + " ").indexOf(" " + sClassName + " ") != -1;
}

/* Table */

/**
 * 对表格进行排序。
 *
 * 该函数用来使表格拥有排序功能。
 * 使用方法：
 * 1, 为要排序的表格定义一个 id 属性值。同时为其 class 属性添加 bordered 类名称。
 * 2, 在表格 thead 部分与要排序的列对应的 th 定义中添加 onclick 事件，格式是：
 *      <th onclick="sortTable('表格 id 属性值', 对应数据列的索引, '数据列数据类型');">
 * 3, 如果表格 thead 部分最后一行的每个 th 与要排序的列无法一一对应（多行表头，有些列上下两行表头有合并时会出现），则须在每个要排序数据
 *    列对应的 th 定义中为 class 属性添加 sort 类名称，同时所添加得 onclick 事件格式也有所改动，具体如下：
 *      <th class="sort" onclick="sortTable('表格 id 属性值', 对应数据列的索引, '数据列数据类型', this);">
 * 数据列类型目前支持的值如下：
 *   link: 数据格式为 <td><a href="...">...</a></td>。
 *   revision: 数据格式为 <td>数字版本: 短格式节点散列值</td>，注意冒号后有一个空格。
 *   date: 数据格式为 <td data-value="Unix 时间戳或可比较日期字符串">日期显示值</td>。
 *   conact: 数据格式为 <td><a href="mailto:....">联系人姓名</a></td>。
 *   number: 数据格式为 <td>数字字符串</td>。
 *   其它:  数据格式为 <td>任何数据</td>，数据将直接比较。
 *
 * @param {string} sTableId - 指定要排序表格的 id 属性值。
 * @param {number} iCol - 指定按照哪一数据列进行排序，当 thead 中最后一行 tr 中每个 th 都对应一列时使用。
 * @param {string} sType - 指定排序数据的类型，目前支持的值有 link、revision、contact、date。
 * @param {string} oTh - 排序列对应的 th 对象。
 */
function sortTable(sTableId, iCol, sType, oTh = null) {
    var oTable = document.getElementById(sTableId);
    var oTBody = oTable.tBodies[0];
    var aRows = oTBody.rows;

    var aDetails = new Array;
    for (var i = 0; i < aRows.length; i++) { if (hasClass(aRows[i], "detail")) { aDetails.push(aRows[i]); } }

    var aTrs = new Array;
    for (var i = 0; i < aRows.length; i++) { if (!hasClass(aRows[i], "detail")) { aTrs.push(aRows[i]); } }

    var oTHead = oTable.getElementsByTagName("thead")[0];
    var aHeads = new Array;
    var iTh = iCol;
    if (oTh == null) {
        var aTHead_Trs = oTHead.getElementsByTagName("tr");
        aHeads = aTHead_Trs[aTHead_Trs.length - 1].getElementsByTagName("th");
    } else {
        aHeads = oTHead.getElementsByClassName("sort");
        for (var i = 0; i < aHeads.length; i++) {
            if (aHeads[i] == oTh) { iTh = i; }
        }
    }
    for (var i = 0; i < aHeads.length; i++) {
        if (i != iTh) { delClass(aHeads[i], "up"); delClass(aHeads[i], "down"); addClass(aHeads[i], "nosort"); }
    }
    if (oTBody.sortCol == iCol) {
        aTrs.reverse();
        if (hasClass(aHeads[iTh], "up")) {
            delClass(aHeads[iTh], "up");
            addClass(aHeads[iTh], "down");
        } else {
            delClass(aHeads[iTh], "down");
            addClass(aHeads[iTh], "up");
        }
    } else {
        aTrs.sort(generateCompareTrs(iCol, sType));
        delClass(aHeads[iTh], "nosort");
        delClass(aHeads[iTh], "down");
        addClass(aHeads[iTh], "up");
    }

    for (var i = 0; i < aTrs.length; i++) {
        delClass(aTrs[i], "parity" + ((i + 1) % 2));
        addClass(aTrs[i], "parity" + (i % 2));
    }

    for (var i = 0; i < aDetails.length; i++) {
        var id = aDetails[i].getAttribute("data-id");
        for (var j = 0; j < aTrs.length; j++) {
            if (aTrs[j].getAttribute("data-id") == id) {
                aTrs.splice(j + 1, 0, aDetails[i]);
                break;
            }
        }
    }

    var oFragment = document.createDocumentFragment();
    for (var i = 0; i < aTrs.length; i++) { oFragment.appendChild(aTrs[i]); }
    oTBody.appendChild(oFragment);
    oTBody.sortCol = iCol;
}

/**
 * 内部排序函数。
 *
 * 该函数由 sortTable 函数使用。
 *
 * @param {number} iCol - 指定按照哪一数据列进行排序，当 thead 中最后一行 tr 中每个 th 都对应一列时使用。
 * @param {string} sType - 指定排序数据的类型，目前支持的值有 link、revision、contact、date。
 * @return {function} - 比较函数。
 */
function generateCompareTrs(iCol, sType) {
    return function compareTrs(oTr1, oTr2) {
        var sValue1 = "";
        var sValue2 = "";
        if (sType == "link") {
            oLink1 = oTr1.cells[iCol].getElementsByTagName("A")[0];
            if (oLink1.firstChild) { sValue1 = oLink1.firstChild.nodeValue; }
            oLink2 = oTr2.cells[iCol].getElementsByTagName("A")[0];
            if (oLink2.firstChild) { sValue2 = oLink2.firstChild.nodeValue; }
        } else if (sType == "number") {
            sValue1 = parseInt(oTr1.cells[iCol].firstChild.nodeValue);
            if (!sValue1) sValue1 = -1;
            sValue2 = parseInt(oTr2.cells[iCol].firstChild.nodeValue);
            if (!sValue2) sValue2 = -1;
            return sValue1 == sValue2 ? 0 : (sValue1 > sValue2 ? 1 : -1);
        } else if (sType == "number1") {
            sValue1 = parseInt(oTr1.cells[iCol].firstChild.firstChild.nodeValue);
            if (!sValue1) sValue1 = -1;
            sValue2 = parseInt(oTr2.cells[iCol].firstChild.firstChild.nodeValue);
            if (!sValue2) sValue2 = -1;
            return sValue1 == sValue2 ? 0 : (sValue1 > sValue2 ? 1 : -1);
        } else if (sType == "size") {
            sValue1 = parseInt(oTr1.cells[iCol].getAttribute("data-size"));
            sValue2 = parseInt(oTr2.cells[iCol].getAttribute("data-size"));
            return sValue1 == sValue2 ? 0 : (sValue1 > sValue2 ? 1 : -1);
        } else if (sType == "revision") {
            sValue1 = parseInt(oTr1.cells[iCol].getElementsByTagName("A")[0].firstChild.nodeValue);
            sValue2 = parseInt(oTr2.cells[iCol].getElementsByTagName("A")[0].firstChild.nodeValue);
            if (!sValue1) sValue1 = -1;
            if (!sValue2) sValue2 = -1;
            return sValue1 == sValue2 ? 0 : (sValue1 > sValue2 ? 1 : -1);
        } else if (sType == "op") {
            sValue1 = oTr1.cells[iCol].getAttribute("data-op");
            sValue2 = oTr2.cells[iCol].getAttribute("data-op");
            return sValue1 == sValue2 ? 0 : (sValue1 > sValue2 ? 1 : -1);
        } else if (sType == "contact") {
            sValue1 = oTr1.cells[iCol].firstChild.nodeName == "A" ?
                oTr1.cells[iCol].firstChild.firstChild.nodeValue + "#" +
                oTr1.cells[iCol].firstChild.getAttribute("href") :
                sValue1 = oTr1.cells[iCol].firstChild.nodeValue;
            sValue2 = oTr2.cells[iCol].firstChild.nodeName == "A" ?
                oTr2.cells[iCol].firstChild.firstChild.nodeValue + "#" +
                oTr2.cells[iCol].firstChild.getAttribute("href") :
                sValue1 = oTr2.cells[iCol].firstChild.nodeValue;
        } else if (sType == "date") {
            sValue1 = oTr1.cells[iCol].getAttribute("data-value");
            sValue2 = oTr2.cells[iCol].getAttribute("data-value");
        } else {
            if (oTr1.cells[iCol].firstChild) sValue1 = oTr1.cells[iCol].firstChild.nodeValue;
            if (oTr2.cells[iCol].firstChild) sValue2 = oTr2.cells[iCol].firstChild.nodeValue;
        }
        if (!sValue1) sValue1 = "";
        if (!sValue2) sValue2 = "";
        return sValue1.localeCompare(sValue2);
    }
}

/**
 * 该函数用来为表格添加折叠功能。
 *
 * 该函数可以让表格拥有展开和折叠功能，在点击表格头时可以展开或折叠整个表格。
 * 使用方法：
 * 1, 为要排序的表格定义一个 id 属性值。同时为其 class 属性添加 bordered 类名称。
 * 2, 在表格 thead 部分的最前面插入一个 tr 行，其中只包含一个合并的 th 项，内容为表格名称，tr 定义中指定 onclick 事件，同时在其
 *    class 属性中添加 up 类名称。格式如下：
 *    <table id="表格 id 属性值">
 *    <thead><tr class="up" onclick="foldTable('表格 id 属性值');"><th colspan="合并列数">表格名称</th></tr>
 * 3, 可以在页面末尾调用该函数来决定页面载入后表格时展开还是折叠状态：
 *    <script type="text/javascript">foldTable('表格 id 属性值', 初始状态);</script>
 *
 * @param {string} sTableId - 要添加折叠功能的表格其 id 属性值。
 * @param {boolean} bAutoExp - 表格初始状态，为真则是展开状态，否则为折叠状态。
 */
function foldTable(sTableId, bAutoExp = false) {
    var oTable = document.getElementById(sTableId);
    var aTrs = oTable.getElementsByTagName("tr");
    Folded = aTrs[0].className == "down";
    for (var i = 1; i < aTrs.length; i++) {
        if (Folded | bAutoExp) {
            if (hasClass(aTrs[i], "detail")) {
                aTrs[i].style.display = hasClass(aTrs[i - 1], "fold-1") ? "none" : "";
            } else {
                aTrs[i].style.display = "";
            }
        } else {
            aTrs[i].style.display = "none";
        }
    }
    aTrs[0].className = (Folded | bAutoExp) ? "up" : "down";
}

/**
 * 该函数用来为表格添加折叠每一行的功能。
 *
 * 该函数可以展开或折叠表格每一行后所跟随的详细信息行。
 * 使用方法：
 *   1, 为要表格定义一个 id 属性值。同时为其 class 属性添加 bordered 类名称。
 *   2, 在表格的最左侧增加一列。thead 中列头的 th 内容可以为空，也可以是文字。
 *   3, 在 tbody 中，每一行数据 tr 行后须跟随一行详细信息 tr 行。详细信息 tr 行中最左侧的列可以与其它列合并，其它各列也可以任意合并。
 *      同时为二者各定义一个具有相同值的 data-id 属性，该值在所有数据中应该唯一。然后为详细信息 tr 行的 class 属性添加 detail 类名
 *      称。
 *   4, 如果初始状态默认折叠详细信息，请在数据 tr 行的 class 属性中添加 fold-1 类。数据 tr 行的首个 td 定义中，其 class 属性中也添
 *      加 fold-1 类，同时添加 onclick 事件，详细信息 tr 行的 style 值则隐藏该详细信息 tr 行：
 *      <tr class="fold-1" data-id="...">
 *        <td class="fold-1" onclick="foldDetail(this, '表格 id 属性值');">&nbsp;</td>
 *        ...
 *      </tr>
 *      <tr class="detail" data-id="..." style="display: none;">.....</tr>
 *   5, 如果初始状态默认展开详细信息，请在数据 tr 行的首个 td 定义中，其 class 属性中添加 fold-0 类，同时添加 onclick 事件：
 *      <tr data-id="...">
 *        <td class="fold-0 onclick="foldDetail(this, '表格 id 属性值');">&nbsp;</td>
 *        ...
 *      </tr>
 *      <tr class="detail" data-id="...">.....</tr>
 *
 * @param {object} oTd - 所点击的 td 对象。
 * @param {string} sTableId - 要添加折叠详细信息功能的表格其 id 属性值。
 */
function foldDetail(oTd, sTableId = "") {
    var oDetail = oTd.parentNode.nextElementSibling;
    if (oTd.className == "fold-1") {
        oDetail.style.display = "";
        oTd.className = "fold-0";
        delClass(oTd.parentNode, "fold-1");
    } else {
        oDetail.style.display = "none";
        oTd.className = "fold-1";
        addClass(oTd.parentNode, "fold-1");
    }
}

/**
 * 该函数用来调整空表格显示。
 *
 * 请在使用表格且表格 class 属性中包含 bordered 类名称的页面末尾执行该函数。
 *    <script type="text/javascript">emptyTable();</script>
 */
function emptyTable() {
    var aTables = document.getElementsByTagName('table');
    for (var i = 0; i < aTables.length; i++) {
        var aTbodies = aTables[i].getElementsByTagName('tbody');
        if (aTbodies.length != 0) {
            if (aTbodies[0].getElementsByTagName("tr").length == 0) {
                aTables[i].removeChild(aTbodies[0]);
            }
        }
    }
}



function focusMenu(actived, pri_actived = "") {
    var oMenuDiv = document.getElementById('_menu');
    var aMenuLi = oMenuDiv.getElementsByTagName('li');
    for (var i = 0; i < aMenuLi.length; i++) {
        var oMenuA = aMenuLi[i].getElementsByTagName("a")[0];
        if (oMenuA.className == actived) {
            aMenuLi[i].className = "actived";
        } else if (oMenuA.className == pri_actived) {
            aMenuLi[i].className = "pri-actived";
        }
    }
}

/**
 * 该函数用来调整变更集页面的显示方式。
 *
 * 请在变更集页面末尾执行该函数，它会将每个文件的差异附加到文件行后面。
 *    <script type="text/javascript">adjustDiffBlock();</script>
 */
function adjustDiffBlock() {
    var aDiffBlock = document.getElementById("diffs").getElementsByTagName("ol");
    var aFileLines = document.getElementById("filediffs-list").getElementsByClassName("detail");
    for (var i = 0; i < aFileLines.length; i++) {
        aFileLines[i].getElementsByTagName("td")[0].appendChild(aDiffBlock[0]);
    }
}

/**
 * 切换主菜单状态。
 *
 * 该函数根据参数的不同执行不同的操作：
 * 请在页面载入后执行 toggleMenu(0)，窗口大小改变后执行 toggleMenu(2)，在点击菜单切换图标时执行 toggleMenu(1)。
 *
 * @param {number} mode - 操作模式：0: 根据窗口大小决定主菜单状态；1: 切换主菜单状态；2: 根据窗口调整后的代销决定主菜单状态。
 */
function toggleMenu(mode = 0) {
    var oMenu = document.getElementsByClassName("_menubar")[0];
    var oToggle = document.getElementById("_toggle");
    if (mode == 0) { // initialize
        if(sessionStorage.getItem("HanHg_menu_expanded")) {
            var expanded = sessionStorage.getItem("HanHg_menu_expanded");
            if (expanded == "1") {
                oMenu.style.width = "192px";
                oToggle.className = "open";
            } else {
                oMenu.style.width = "32px";
                oToggle.className = "fold";
            }
        } else {
            document.originalWidth = document.documentElement.clientWidth;
            if(document.documentElement.clientWidth < 800) {
                oMenu.style.width = "32px";
                oToggle.className = "fold";
            };
            if(document.documentElement.clientWidth > 800) {
                oMenu.style.width = "192px";
                oToggle.className = "open";
            };
        }
    } else if (mode == 1) { // click on menu
        if (oToggle.className == "fold") {  // 1px for border-right
            oMenu.style.width = "192px";
            oToggle.className = "open";
        } else {
            oMenu.style.width = "32px";
            oToggle.className = "fold";
        }
    } else if (mode == 2) { // resize window
        if ((document.documentElement.clientWidth < 800) &&
            (document.documentElement.clientWidth < document.originalWidth)) {
            oMenu.style.width = "32px";
            oToggle.className = "fold";
        };
        if ((document.documentElement.clientWidth > 900) &&
            (document.documentElement.clientWidth > document.originalWidth)) {
            oMenu.style.width = "192px";
            oToggle.className = "open";
        };
    }
    document.originalWidth = document.documentElement.clientWidth;
    if (oToggle.className == "open") {
        sessionStorage.setItem("HanHg_menu_expanded", "1");
    } else {
        sessionStorage.setItem("HanHg_menu_expanded", "0");
    }
}

window.onresize = function() {
    toggleMenu(2);
};

/**
 * 该函数用来验证创建存贮库表单的输入。
 *
 * @param {object} form - 表单名称。
 * @return {boolean} - 验证结构，验证成功则返回真，否则返回假。
 */
function validate(form) {
    var valid = true;
    if (!form.realm.value.match(/.{3,30}/) || form.realm.value.match(/[`&%#?*^{}\\\[\]]/)) {
        form.realm.style.borderColor = "#ff0000";
        form.realm.focus();
        valid = false;
    }
    if (!form.repo.value.match(/^[a-zA-z0-9\.\-_]{3,30}$/)) {
        form.repo.style.borderColor = "#ff0000";
        form.repo.focus();
        valid = false;
    }
    if (!valid) showErrorHint("error-hint");
    return valid;
}

/**
 * 该函数用来恢复错误表单项的高亮提示。
 *
 * @param {object} field - 表单项 id。
 */
function restoreField(field) {
    field.style.borderColor = "#e0e0e0";
}

/**
 * 该函数用来显示表单项错误提示框。
 *
 * @param {string} error - 错误消息。
 */
function showErrorHint(error) {
    var err_hint = document.getElementById(error);
    err_hint.className = "displaied";
    err_hint.style.display = "block";
    setTimeout( function() { hideErrorHint0(); }, 2000);
}

/**
 * 该函数用来隐藏表单项错误提示框。
 *
 * 执行后错误提示框回渐渐淡出，并引发 hideErrorHint1 函数执行。
 */
function hideErrorHint0() {
    var err_hint = document.getElementById("error-hint");
    err_hint.className = "hidden";
    setTimeout( function() { hideErrorHint1(); }, 1000);
}

/**
 * 该函数用来彻底隐藏表单项错误提示框，以便不影响输入。
 *
 * 该函数由 hideErrorHint0 调用。
 */
function hideErrorHint1() {
    var err_hint = document.getElementById("error-hint");
    err_hint.style.display = "none";
}

/**
 * 该函数用来取得请求链接末尾某个参数的值。
 *
 * @param {string} name - 参数名称。
 * @return {string} - 参数值。
 */
function get(name){
   if(name=(new RegExp('[?&]'+encodeURIComponent(name)+'=([^&]*)')).exec(location.search))
      return decodeURIComponent(name[1]);
}

/**
 * 该函数用来在代码库列表页突出显示刚刚建立的代码库。
 */
function focusNewRepo() {
    var repo = get("repo");
    if (repo != undefined) {
        var oList = document.getElementById("repos-list");
        var aTrs = oList.getElementsByTagName("tbody")[0].getElementsByTagName("tr");
        for (var i = 0; i < aTrs.length; i++) {
            if (repo == aTrs[i].getAttribute("data-repo")) {
                aTrs[i].style.backgroundColor = "#ffaa00";
                setTimeout( function() { normalNewRepo(); }, 2000);
                break;
            }
        }
    }
}

/**
 * 该函数用来在代码库列表页渐渐撤销对刚刚建立的代码库的突出显示。由 focusNewRepo 函数调用。
 */
function normalNewRepo() {
    var repo = get("repo");
    if (repo != undefined) {
        var oList = document.getElementById("repos-list");
        var aTrs = oList.getElementsByTagName("tbody")[0].getElementsByTagName("tr");
        for (var i = 0; i < aTrs.length; i++) {
            if (repo == aTrs[i].getAttribute("data-repo")) {
                aTrs[i].style.backgroundColor = "";
                break;
            }
        }
    }
}

/**
 * 该函数用来在文件源页面显示图片文件的内容。
 */
function displayImage() {
    var oImg = document.getElementById("source-img");
    var sDisplay = oImg.getAttribute('data-display');
    var sSvg = oImg.getAttribute('data-svg');
    if ((sDisplay == 'yes') && (sSvg == 'no')) {
        oImg.setAttribute("src", oImg.getAttribute("data-src"));
        oImg.style.display = "inline-block";
    }
}

/**
 * 该函数用来在文件源页面为源代码显示行号。
 */
function addLineno() {
    var oCode = document.getElementsByTagName("code");
    for (var i = 0; i < oCode.length; i++) {
        var iLines = oCode[i].firstChild.nodeValue.split('\n').length;
        var oPre = oCode[i].parentNode;
        var oUl = oPre.getElementsByTagName("ul")[0];
        for (var j = 1; j <= iLines; j++) {
            var oLi = document.createElement("li");
            oLi.innerHTML = j;
            oUl.appendChild(oLi);
        }
    }
}

/**
 * 该函数删除代码块语法高亮。
 */
function deleteSyntax() {
    var oCode = document.getElementsByTagName("code")[0];
    delClass(oCode, "nohighlight");
    delClass(oCode, "accesslog");
    delClass(oCode, "actionscript");
    delClass(oCode, "apache");
    delClass(oCode, "applescript");
    delClass(oCode, "armasm");
    delClass(oCode, "bash");
    delClass(oCode, "basic");
    delClass(oCode, "clean");
    delClass(oCode, "cmake");
    delClass(oCode, "coffeescript");
    delClass(oCode, "cpp");
    delClass(oCode, "cs");
    delClass(oCode, "css");
    delClass(oCode, "delphi");
    delClass(oCode, "diff");
    delClass(oCode, "dns");
    delClass(oCode, "dockerfile");
    delClass(oCode, "dos");
    delClass(oCode, "erlang");
    delClass(oCode, "go");
    delClass(oCode, "http");
    delClass(oCode, "ini");
    delClass(oCode, "java");
    delClass(oCode, "javascript");
    delClass(oCode, "json");
    delClass(oCode, "makefile");
    delClass(oCode, "markdown");
    delClass(oCode, "mercury");
    delClass(oCode, "nginx");
    delClass(oCode, "objectivec");
    delClass(oCode, "perl");
    delClass(oCode, "php");
    delClass(oCode, "powershell");
    delClass(oCode, "profile");
    delClass(oCode, "python");
    delClass(oCode, "ruby");
    delClass(oCode, "scheme");
    delClass(oCode, "shell");
    delClass(oCode, "sml");
    delClass(oCode, "sql");
    delClass(oCode, "swift");
    delClass(oCode, "tex");
    delClass(oCode, "vhdl");
    delClass(oCode, "xml");
    delClass(oCode, "xquery");
}

/**
 * 该函数对代码块使用指定的语法高亮。
 *
 * @param {string} lang - 要应用语法高亮的语言名称。
 */
function changeSyntax(lang) {
    var oCode = document.getElementsByTagName("code")[0];
    deleteSyntax();
    if (lang != "auto") { addClass(oCode, lang); }
    if (lang != "nohighlight") { hljs.highlightBlock(oCode); }
}

function displayMarkdown() {
    var md_src = document.getElementById('md-src');
    var md_des = document.getElementById('md-des');
    var src_content = md_src.firstChild.nodeValue;
    if (src_content != '-') { md_des.innerHTML = markdown.toHTML(src_content, "Maruku"); }
}

function adjustCreate() {
    var folder = document.getElementById('folder');
    var folder_value = folder.getAttribute('data-sel');
    var sels = folder.getElementsByTagName('option');
    for (var i = 0; i < sels.length; i++) {
        if (sels[i].getAttribute('value') == folder_value) {
            folder[i].selected = true;
            break;
        }
    }
    var format = document.getElementById('repo-fmt-box').getAttribute('data-fmt');
    var fmt_box = document.getElementById('fmt_' + format);
    if (fmt_box) { fmt_box.setAttribute('checked', 'checked'); }
}
