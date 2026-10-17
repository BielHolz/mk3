document.querySelectorAll('[data-photo]').forEach(b=>b.addEventListener('click',()=>{document.getElementById('main-photo').src=b.dataset.photo;}));
