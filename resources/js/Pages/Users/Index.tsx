import { createIndexPage } from '@/Components/ResourcePage'; export default createIndexPage({title:'Users',collectionKey:'users',resourcePath:'/users',columns:['name','email','role','is_active']});
