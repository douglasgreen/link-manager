document.addEventListener('DOMContentLoaded', function() {
    // Edit Bookmark Modal
    const editBookmarkModal = document.getElementById('editBookmarkModal');
    if (editBookmarkModal) {
        editBookmarkModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('editBookmarkId').value = button.dataset.bookmarkId;
            document.getElementById('editBookmarkTitle').value = button.dataset.bookmarkTitle;
            document.getElementById('editBookmarkUrl').value = button.dataset.bookmarkUrl;
            document.getElementById('editBookmarkDescription').value = button.dataset.bookmarkDescription || '';
            document.getElementById('editBookmarkGroup').value = button.dataset.bookmarkGroup;
        });
    }

    // Edit Group Modal
    const editGroupModal = document.getElementById('editGroupModal');
    if (editGroupModal) {
        editGroupModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('editGroupId').value = button.dataset.groupId;
            document.getElementById('editGroupName').value = button.dataset.groupName;
            document.getElementById('editGroupDescription').value = button.dataset.groupDescription || '';
        });
    }

    // Auto-close offcanvas navbar after a group link is clicked
    const offcanvasNav = document.getElementById('offcanvasNav');
    if (offcanvasNav) {
        const navLinks = offcanvasNav.querySelectorAll('.list-group-item a');
        const bsOffcanvas = new bootstrap.Offcanvas(offcanvasNav);
        
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                bsOffcanvas.hide();
            });
        });
    }
});
