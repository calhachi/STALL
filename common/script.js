
// maxlengthに合わせてテキストボックスにカウンターを表示
const inputs = document.querySelectorAll('.chara-limit');

inputs.forEach((input) => {
    const maxLength = input.dataset.maxlength;
    const counter = input.nextElementSibling;

    input.addEventListener('input', () => {
        const length = input.value.length;

        counter.textContent = `${length} / ${maxLength}`;

        if (length > maxLength) {
            counter.style.color = 'red';
        } else {
            counter.style.color = '';
        }
    });
});

// 登録画面のid=previewの画像が選択されたらimgデフォ画像と表示入れ替え
const aImageInput = document.getElementById('aImageInput');
const aImagePreview = document.getElementById('aImagePreview');

if (aImageInput && aImagePreview
) {
    aImageInput.addEventListener('change', function () {
        const file = this.files[0];

        if (file) {
            aImagePreview.src = URL.createObjectURL(file);
        }
    });
}




// 親カテゴリ参照してサブカテゴリ・タグ表示
const categorySelect = document.getElementById('categorySelect');

const subCategorySelect =
    document.getElementById('subCategorySelect');

const tagSelects =
    document.querySelectorAll('.tagSelect');

const trpgOnlyFields =
    document.getElementById('trpgOnlyFields');


// selectにoptionを追加する共通関数
function setOptions({
    target,
    items,
    valueKey = 'id',
    textKey = 'name',
    selectedValue = ''
}) {

    target.innerHTML = '<option value="">-----</option>';

    items.forEach(item => {

        const option = document.createElement('option');

        option.value = item[valueKey];
        option.textContent = item[textKey];

        if (item[valueKey] == selectedValue) {
            option.selected = true;
        }

        target.appendChild(option);
    });
}


function updateCategoryRelated() {

    const categoryId = categorySelect.value;

    // TRPGカテゴリ(id=1)以外なら隠す
    if (categoryId != 1) {

        trpgOnlyFields.style.display = 'none';

    } else {

        trpgOnlyFields.style.display = 'block';
    }
    // カテゴリ未選択
    if (categoryId === '') {

        subCategorySelect.innerHTML =
            '<option value="">カテゴリを選択してください</option>';

        tagSelects.forEach(select => {
            select.innerHTML =
                '<option value="">カテゴリを選択してください</option>';
        });

        return;
    }

    // サブカテゴリ更新
    const filteredSubCategories =
        subCategories.filter(subCategory =>
            subCategory.category_id == categoryId
        );

    setOptions({
        target: subCategorySelect,
        items: filteredSubCategories,
        textKey: 'name',
        selectedValue: selectedSubCategoryId
    });


    // タグ更新
    const filteredTags =
        tags.filter(tag =>
            tag.category_id == categoryId
        );

    tagSelects.forEach((select, index) => {

        setOptions({
            target: select,
            items: filteredTags,
            textKey: 'tag_name',
            selectedValue: selectedTagsId[index] ?? ''
        });
    });
}


if (categorySelect && subCategorySelect) {

    categorySelect.addEventListener(
        'change',
        updateCategoryRelated
    );

    updateCategoryRelated();
}



// // 作品カテゴリ参照してサブカテゴリ表示
// const categorySelect = document.getElementById('categorySelect');
// const subCategorySelect = document.getElementById('subCategorySelect');

// function updateSubCategories() {

//     const selectedCategoryId = categorySelect.value;

//     subCategorySelect.innerHTML = '';

//     if (selectedCategoryId === '') {
//         subCategorySelect.innerHTML =
//             '<option value="">作品カテゴリを選択してください</option>';
//         return;
//     }

//     const filteredSubCategories = subCategories.filter(subCategory =>
//         subCategory.category_id == selectedCategoryId
//     );

//     filteredSubCategories.forEach(subCategory => {

//         const option = document.createElement('option');

//         option.value = subCategory.id;
//         option.textContent = subCategory.name;

//         if (subCategory.id == selectedSubCategoryId) {
//             option.selected = true;
//         }

//         subCategorySelect.appendChild(option);
//     });
// }

// if (categorySelect && subCategorySelect) {

//     categorySelect.addEventListener('change', updateSubCategories);

//     // 初回表示時にも実行
//     updateSubCategories();
// }



// // .imagesInputをつけたinputで選択された画像を近くのdiv.imagesPreviewの位置に並べる
// const uploaders = document.querySelectorAll('.imageUploader');

// uploaders.forEach(function (uploader) {

//     const imagesInput = uploader.querySelector('.imagesInput');
//     const imagesPreview = uploader.querySelector('.imagesPreview');

//     imagesInput.addEventListener('change', function () {

//         imagesPreview.innerHTML = '';

//         for (const file of this.files) {

//             const image = document.createElement('img');

//             image.src = URL.createObjectURL(file);

//             image.style.width = '150px';
//             image.style.margin = '10px';

//             imagesPreview.appendChild(image);

//         }

//     });

// });

// .imagesInputをつけたinputで選択された画像を近くのdiv.imagesPreviewの位置に並べるVer.2
const uploaders = document.querySelectorAll('.imageUploader');

uploaders.forEach(function (uploader) {

    const imagesInput = uploader.querySelector('.imagesInput');
    const imagesPreview = uploader.querySelector('.imagesPreview');
    imagesInput.addEventListener('change', function () {

        const currentImage = uploader.querySelector('.currentImage');

        if (currentImage) {
            currentImage.remove();
        }

        imagesPreview.innerHTML = '';

        for (const file of this.files) {

            const image = document.createElement('img');

            image.src = URL.createObjectURL(file);

            image.style.width = '150px';
            image.style.margin = '10px';

            imagesPreview.appendChild(image);

        }

    });
});


// 画面遷移なしで作品削除
const deleteButtons = document.querySelectorAll('.deleteButton');

for (const button of deleteButtons) {

    button.addEventListener('click', async function (event) {

        // カードリンクへのイベント伝播防止
        event.preventDefault();
        event.stopPropagation();

        const isOk = confirm('この操作は取り消せません。\n本当に削除しますか？');

        if (!isOk) {
            return;
        }

        const workId = this.dataset.workId;

        const response = await fetch(`${appUrl}/api/deleteWork.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                workId: workId
            })
        });

        const result = await response.json();

        if (result.success) {

            // カード消す
            this.closest('.worksCard').remove();

        } else {

            alert(result.message);
        }
    });
}